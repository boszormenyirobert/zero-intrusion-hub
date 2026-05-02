<?php

namespace App\Service\User\Login\HUB;

use App\DTO\LoginRequestDTO;
use App\DTO\LoginViewDataDTO;
use App\DTO\PollRequestDTO;
use App\DTO\RejectedProcessStateDTO;
use App\Entity\User;
use App\Logger\LogTrace;
use App\Repository\ProcessRepository;
use App\Repository\UserRepository;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use App\Service\JWT\JwtService;
use App\Service\Shared\ProcessKey;
use App\Service\User\UserQrCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoginService
{
    private const ACTION_LOGIN = 'login';
    private const ACTION_REGISTRATION = 'registration';
    private const MAX_WAIT_SECONDS_FIRST_USER = 30;
    private const MAX_WAIT_SECONDS_DEFAULT = 10;
    private const ROUTE_INSTANCE_LOGIN = 'instance_login';
    private const ROUTE_INSTANCE_LOGOUT = 'instance_logout';
    private const ROUTE_USER_LOGIN_CHECK = 'user_login_check_hub';

    public function __construct(
        private LoggerInterface $logger,
        private UserQrCodeService $userQrCodeService,
        private ProcessRepository $processRepository,
        private UserRepository $userRepository,
        private RegistrationMenuAvailabilityService $registrationMenuAvailabilityService,
        private JwtService $jwtService,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function buildLoginViewData(Request $request): LoginViewDataDTO
    {
        $availabilities = $this->registrationMenuAvailabilityService->getAvailability($request);

        $this->logger->info('HUB login page requested', [
            'route' => self::ROUTE_INSTANCE_LOGIN,
            'user_public_id_present' => $request->query->has('userPublicId'),
            'allowUsersMenu' => $availabilities->toArray(),
        ]);

        $loginRequest = $this->resolveLoginRequest($request);

        $response = $this->userQrCodeService->getQrCode(ProcessKey::USER_LOGIN, null, $loginRequest->userPublicId);

        if (!$response->hasLoginFields()) {
            $this->logger->error('Login QR code response is missing required fields', [
                'route' => self::ROUTE_INSTANCE_LOGIN,
                'response_keys' => array_keys($response->toArray()),
            ]);

            throw new HttpException(502, 'Login QR code response is incomplete.');
        }

        $this->logger->info('Login QR code generated', [
            'route' => self::ROUTE_INSTANCE_LOGIN,
            'process' => ProcessKey::USER_LOGIN,
            'domain_process_id' => $response->getDomainProcessId(),
            'user_public_id_present' => $loginRequest->userPublicId !== null,
        ]);

        return new LoginViewDataDTO(
            (string) $response->getDomainProcessId(),
            $response,
            (string) $response->getQrCode(),
            [],
            self::ACTION_LOGIN,
            $availabilities
        );
    }

    public function buildFrontendPollResponse(
        Request $request
    ): JsonResponse {
        $pollRequest = $this->buildPollRequest($request);

        if ($pollRequest === null) {
            return $this->createMessageResponse('Invalid poll request.', Response::HTTP_BAD_REQUEST);
        }

        $user = $this->pollState($pollRequest->processId, $pollRequest->action);

        if ($pollRequest->action === self::ACTION_LOGIN && $user instanceof User && $user->isAllowed()) {
            return $this->buildSuccessfulLoginResponse(
                $user,
                $pollRequest->processId,
                $pollRequest->action
            );
        }

        if ($pollRequest->action === self::ACTION_REGISTRATION && $user instanceof User) {
            return $this->buildSuccessfulRegistrationResponse(
                $user,
                $pollRequest->processId,
                $pollRequest->action
            );
        }

        if ($pollRequest->action === self::ACTION_REGISTRATION && $user instanceof RejectedProcessStateDTO) {
            return $this->buildRejectedRegistrationResponse(
                $pollRequest->processId,
                $pollRequest->action,
                $user->reason ?? null
            );
        }

        if ($pollRequest->action === self::ACTION_LOGIN && $user instanceof RejectedProcessStateDTO) {
            return $this->buildRejectedLoginResponse(
                $pollRequest->processId,
                $pollRequest->action,
                $user->reason ?? null
            );
        }

        return $this->buildFailedPollResponse($pollRequest->processId, $pollRequest->action);
    }

    public function prepareLogoutResponse(Response $response, Request $request): void
    {
        $this->logger->info('User logout requested', [
            'route' => self::ROUTE_INSTANCE_LOGOUT,
            'host' => $request->getHost(),
            'has_jwt_cookie' => $request->cookies->has($this->jwtService->getCookieName()),
        ]);

        $this->jwtService->clearAuthenticationCookie($response, $request);

        $this->logger->info('User logout response prepared', [
            'route' => self::ROUTE_INSTANCE_LOGOUT,
            'host' => $request->getHost(),
        ]);
    }

    private function buildPollRequest(Request $request): ?PollRequestDTO
    {
        $processId = $this->extractRequiredQueryString($request, 'processId');
        $action = $this->extractRequiredQueryString($request, 'action');

        if ($processId === null) {
            $this->logger->warning('Frontend login poll rejected due to missing processId', [
                'route' => self::ROUTE_USER_LOGIN_CHECK,
            ]);

            return null;
        }

        if ($action === null) {
            $this->logger->warning('Frontend login poll rejected due to missing action', [
                'route' => self::ROUTE_USER_LOGIN_CHECK,
            ]);

            return null;
        }

        if (!$this->isSupportedAction($action)) {
            $this->logger->warning('Frontend login poll rejected due to invalid action', [
                'route' => self::ROUTE_USER_LOGIN_CHECK,
                'action' => $action,
            ]);

            return null;
        }

        $this->logger->info('Frontend login poll started', [
            'route' => self::ROUTE_USER_LOGIN_CHECK,
            'process_hash' => LogTrace::fingerprint($processId),
            'action' => $action,
        ]);

        return new PollRequestDTO($processId, $action);
    }

    private function buildSuccessfulLoginResponse(
        User $user,
        string $processId,
        string $action
    ): JsonResponse {
        $response = new JsonResponse([
            'message' => 'Authentication success.',
        ]);

        $response->headers->setCookie($this->jwtService->createAuthenticationCookie($user));
        $this->resetUserLoginState($user);
        $this->logger->info('Frontend login poll succeeded', [
            'route' => self::ROUTE_USER_LOGIN_CHECK,
            'process_hash' => LogTrace::fingerprint($processId),
            'action' => $action,
            'user_id' => $user->getId(),
        ]);

        return $response;
    }

    private function buildSuccessfulRegistrationResponse(
        User $user,
        string $processId,
        string $action
    ): JsonResponse {
        $this->logger->info('Frontend registration poll succeeded', [
            'route' => self::ROUTE_USER_LOGIN_CHECK,
            'process_hash' => LogTrace::fingerprint($processId),
            'action' => $action,
            'user_id' => $user->getId(),
        ]);

        return $this->createMessageResponse('Registration success.');
    }

    private function buildRejectedRegistrationResponse(string $processId, string $action, ?string $reason): JsonResponse
    {
        $message = match ($reason) {
            'registration_rejected_duplicate_user' => 'Registration rejected: this email and public ID are already registered.',
            'registration_rejected_whitelist' => 'Registration rejected: this email address is not allowed.',
            default => 'Registration rejected.',
        };

        $this->logger->warning('Frontend registration poll detected rejected registration', [
            'route' => self::ROUTE_USER_LOGIN_CHECK,
            'process_hash' => LogTrace::fingerprint($processId),
            'action' => $action,
            'reason' => $reason,
        ]);

        return $this->createMessageResponse($message);
    }

    private function buildRejectedLoginResponse(string $processId, string $action, ?string $reason): JsonResponse
    {
        $message = match ($reason) {
            'login_rejected_whitelist' => 'Authentication rejected: access has been revoked for this email address.',
            default => 'Authentication rejected.',
        };

        $this->logger->warning('Frontend login poll detected rejected authentication', [
            'route' => self::ROUTE_USER_LOGIN_CHECK,
            'process_hash' => LogTrace::fingerprint($processId),
            'action' => $action,
            'reason' => $reason,
        ]);

        return $this->createMessageResponse($message);
    }

    private function buildFailedPollResponse(string $processId, string $action): JsonResponse
    {
        $this->logger->warning('Frontend login poll finished without successful authentication', [
            'route' => self::ROUTE_USER_LOGIN_CHECK,
            'process_hash' => LogTrace::fingerprint($processId),
            'action' => $action,
        ]);

        $message = $action === 'registration'
            ? 'Registration timed out. Please try again.'
            : 'Authentication timed out. Please try again.';

        return $this->createMessageResponse($message);
    }

    private function resetUserLoginState(User $user): void
    {
        $user->setAllowed(false);
        $user->setProcess(null);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function resolveLoginRequest(Request $request): LoginRequestDTO
    {
        $userPublicId = $this->extractOptionalQueryString($request, 'userPublicId');

        if ($userPublicId !== null) {
            $this->assertValidUserPublicId($userPublicId);
        }

        return new LoginRequestDTO($userPublicId);
    }

    private function assertValidUserPublicId(string $userPublicId): void
    {
        $this->logger->info('userPublicId received on login request', [
            'route' => self::ROUTE_INSTANCE_LOGIN,
            'user_public_id_hash' => LogTrace::fingerprint($userPublicId),
            'userPublicId_length' => strlen($userPublicId),
        ]);

        if (strlen($userPublicId) < 42 || strlen($userPublicId) > 50) {
            $this->logger->error('Invalid userPublicId length on login request', [
                'route' => self::ROUTE_INSTANCE_LOGIN,
                'user_public_id_length' => strlen($userPublicId),
                'exception' => \InvalidArgumentException::class,
            ]);

            throw new \InvalidArgumentException('Invalid length.');
        }

        if (!preg_match('/^[A-Za-z0-9+\/ ]+={0,2}$/', $userPublicId)) {
            $this->logger->error('Invalid userPublicId characters on login request', [
                'route' => self::ROUTE_INSTANCE_LOGIN,
                'exception' => \InvalidArgumentException::class,
            ]);

            throw new \InvalidArgumentException('Invalid characters.');
        }

        $decoded = base64_decode($userPublicId, true);

        if ($decoded === false || strlen($decoded) < 32 || strlen($decoded) > 40) {
            $this->logger->error('Invalid userPublicId payload on login request', [
                'route' => self::ROUTE_INSTANCE_LOGIN,
                'decoded_length' => $decoded === false ? null : strlen($decoded),
                'exception' => \InvalidArgumentException::class,
            ]);

            throw new \InvalidArgumentException('Invalid token.');
        }
    }

    private function pollState(string $processId, string $action): RejectedProcessStateDTO|\App\Entity\User|null
    {
        $startTime = time();
        $maxWait = $this->resolveMaxWaitSeconds();
        $response = null;

        $this->logger->info('Polling started for frontend state check', [
            'process_hash' => LogTrace::fingerprint($processId),
            'action' => $action,
            'max_wait_seconds' => $maxWait,
        ]);

        do {
            $user = $this->findUserByProcessId($processId);

            if ($action === self::ACTION_LOGIN && $user && $user->isAllowed()) {
                $response = $user;
                $this->logger->info('Polling found login-ready user', [
                    'process_hash' => LogTrace::fingerprint($processId),
                    'action' => $action,
                    'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                ]);
                break;
            }

            if ($action === self::ACTION_REGISTRATION && $user) {
                $response = $user;
                $this->logger->info('Polling found registration-ready user', [
                    'process_hash' => LogTrace::fingerprint($processId),
                    'action' => $action,
                    'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                    'user_allowed' => method_exists($user, 'isAllowed') ? $user->isAllowed() : null,
                ]);
                break;
            }

            if ($action === self::ACTION_REGISTRATION && $this->isRegistrationRejected($processId)) {
                $response = $this->buildRejectedRegistrationState($processId);
                break;
            }

            if ($action === self::ACTION_LOGIN && $this->isLoginRejected($processId)) {
                $response = $this->buildRejectedLoginState($processId);
                break;
            }

            if ((time() - $startTime) >= $maxWait) {
                $matchingUser = $this->findUserByProcessId($processId);

                $this->logger->warning('Polling timed out without matching user state', [
                    'process_hash' => LogTrace::fingerprint($processId),
                    'action' => $action,
                    'max_wait_seconds' => $maxWait,
                    'matching_user_found' => $matchingUser !== null,
                    'matching_user_id' => $matchingUser !== null && method_exists($matchingUser, 'getId') ? $matchingUser->getId() : null,
                    'matching_user_allowed' => $matchingUser !== null && method_exists($matchingUser, 'isAllowed') ? $matchingUser->isAllowed() : null,
                    'registration_rejected' => $action === self::ACTION_REGISTRATION ? $this->isRegistrationRejected($processId) : null,
                    'login_rejected' => $action === self::ACTION_LOGIN ? $this->isLoginRejected($processId) : null,
                ]);
                break;
            }

            usleep(500000);
        } while (true);

        return $response;
    }

    private function isRegistrationRejected(string $processId): bool
    {
        return $this->processRepository->findRejectedRegistrationProcess($processId) !== null;
    }

    private function isLoginRejected(string $processId): bool
    {
        return $this->processRepository->findRejectedLoginProcess($processId) !== null;
    }

    private function buildRejectedRegistrationState(string $processId): RejectedProcessStateDTO
    {
        $process = $this->processRepository->findRejectedRegistrationProcess($processId);

        if ($process === null) {
            return new RejectedProcessStateDTO();
        }

        $this->logger->info('Detected rejected registration process during polling', [
            'process_hash' => LogTrace::fingerprint($processId),
            'reason' => $process->getAuthId(),
        ]);

        return new RejectedProcessStateDTO('rejected', $process->getAuthId());
    }

    private function buildRejectedLoginState(string $processId): RejectedProcessStateDTO
    {
        $process = $this->processRepository->findRejectedLoginProcess($processId);

        if ($process === null) {
            return new RejectedProcessStateDTO();
        }

        $this->logger->info('Detected rejected login process during polling', [
            'process_hash' => LogTrace::fingerprint($processId),
            'reason' => $process->getAuthId(),
        ]);

        return new RejectedProcessStateDTO('rejected', $process->getAuthId());
    }

    private function resolveMaxWaitSeconds(): int
    {
        return $this->userRepository->count([]) === 0
            ? self::MAX_WAIT_SECONDS_FIRST_USER
            : self::MAX_WAIT_SECONDS_DEFAULT;
    }

    private function findUserByProcessId(string $processId): ?\App\Entity\User
    {
        return $this->userRepository->findOneBy([
            'process' => $processId,
        ]);
    }

    private function extractRequiredQueryString(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function extractOptionalQueryString(Request $request, string $key): ?string
    {
        if (!$request->query->has($key)) {
            return null;
        }

        $value = $request->query->get($key);

        if (!is_string($value)) {
            $this->logger->error('Missing userPublicId value on login request', [
                'route' => self::ROUTE_INSTANCE_LOGIN,
                'exception' => BadRequestHttpException::class,
            ]);

            throw new BadRequestHttpException('Missing userPublicId value.');
        }

        return $value;
    }

    private function isSupportedAction(string $action): bool
    {
        return in_array($action, [self::ACTION_LOGIN, self::ACTION_REGISTRATION], true);
    }

    private function createMessageResponse(string $message, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
