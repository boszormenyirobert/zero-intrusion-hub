<?php

namespace App\Service\User\Login\HUB;

use App\Controller\User\UserService;
use App\Repository\ProcessRepository;
use App\Repository\UserRepository;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\BadGatewayHttpException;

class LoginService
{
    public function __construct(
        private LoggerInterface $logger,
        private UserService $userService,
        private ProcessRepository $processRepository,
        private UserRepository $userRepository,
        private RegistrationMenuAvailabilityService $registrationMenuAvailabilityService,
        private EntityManagerInterface $entityManager
    ) {}

    public function buildLoginViewData(Request $request): array
    {
        $availabilities = $this->registrationMenuAvailabilityService->getAvailability($request);

        $this->logger->info('HUB login page requested', [
            'route' => 'instance_login',
            'user_public_id_present' => $request->query->has('userPublicId'),
            'allowUsersMenu' => $availabilities,
        ]);

        $loginRequest = $this->resolveLoginRequest($request);

        $response = $this->userService->getQrCode('user_login', [], $loginRequest['userPublicId']);

        if (!isset($response['domainProcessId'], $response['qrCode'])) {
            $this->logger->error('Login QR code response is missing required fields', [
                'route' => 'instance_login',
                'response_keys' => array_keys($response),
            ]);

            throw new BadGatewayHttpException('Login QR code response is incomplete.');
        }

        $this->logger->info('Login QR code generated', [
            'route' => 'instance_login',
            'process' => 'user_login',
            'domain_process_id' => $response['domainProcessId'] ?? null,
            'user_public_id_present' => $loginRequest['userPublicId'] !== null,
        ]);

        return [
            'processId' => $response['domainProcessId'],
            'qrCodeData' => $response,
            'qrCode' => $response['qrCode'],
            'user' => [],
            'action' => 'login',
            'availabilities' => $availabilities
        ];
    }

    public function buildFrontendPollResponse(
        Request $request,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $pollRequest = $this->buildPollRequest($request);

        if ($pollRequest === null) {
            return new JsonResponse([
                'message' => 'Invalid poll request.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->pollState($pollRequest['processId'], $pollRequest['action']);

        if ($this->isSuccessfulLogin($pollRequest['action'], $user)) {
            return $this->buildSuccessfulLoginResponse(
                $user,
                $jwtManager,
                $pollRequest['processId'],
                $pollRequest['action']
            );
        }

        if ($this->isSuccessfulRegistration($pollRequest['action'], $user)) {
            return $this->buildSuccessfulRegistrationResponse(
                $user,
                $pollRequest['processId'],
                $pollRequest['action']
            );
        }

        if ($this->isRejectedRegistration($pollRequest['action'], $user)) {
            return $this->buildRejectedRegistrationResponse(
                $pollRequest['processId'],
                $pollRequest['action'],
                $user['reason'] ?? null
            );
        }

        if ($this->isRejectedLogin($pollRequest['action'], $user)) {
            return $this->buildRejectedLoginResponse(
                $pollRequest['processId'],
                $pollRequest['action'],
                $user['reason'] ?? null
            );
        }

        return $this->buildFailedPollResponse($pollRequest['processId'], $pollRequest['action']);
    }

    public function prepareLogoutResponse(Response $response, Request $request): void
    {
        $this->logger->info('User logout requested', [
            'route' => 'instance_logout',
            'host' => $request->getHost(),
            'has_jwt_cookie' => $request->cookies->has('jwt_token'),
        ]);

        $response->headers->clearCookie('jwt_token');
        $response->headers->clearCookie('jwt_token', '/', null, false, true, null, 'Strict');

        $host = $request->getHost();
        if ($host !== '') {
            $response->headers->clearCookie('jwt_token', '/', $host, false, true, null, 'Strict');
        }

        $response->headers->setCookie(new Cookie(
            'jwt_token',
            '',
            time() - 3600,
            '/',
            null,
            false,
            true,
            false,
            'Strict'
        ));

        $this->logger->info('User logout response prepared', [
            'route' => 'instance_logout',
            'host' => $request->getHost(),
        ]);
    }

    private function buildPollRequest(Request $request): ?array
    {
        $processId = $request->query->get('processId');
        $action = $request->query->get('action');

        if (!is_string($processId) || trim($processId) === '') {
            $this->logger->warning('Frontend login poll rejected due to missing processId', [
                'route' => 'user_login_check',
            ]);

            return null;
        }

        if (!is_string($action)) {
            $this->logger->warning('Frontend login poll rejected due to missing action', [
                'route' => 'user_login_check',
            ]);

            return null;
        }

        $processId = trim($processId);
        $action = trim($action);

        if (!in_array($action, ['login', 'registration'], true)) {
            $this->logger->warning('Frontend login poll rejected due to invalid action', [
                'route' => 'user_login_check',
                'action' => $action,
            ]);

            return null;
        }

        $this->logger->info('Frontend login poll started', [
            'route' => 'user_login_check',
            'process' => $processId,
            'action' => $action,
        ]);

        return [
            'processId' => $processId,
            'action' => $action,
        ];
    }

    private function isSuccessfulLogin(string $action, mixed $user): bool
    {
        return $action === 'login' && $user && is_object($user) && $user->isAllowed();
    }

    private function isSuccessfulRegistration(string $action, mixed $user): bool
    {
        return $action === 'registration' && $user && is_object($user);
    }

    private function isRejectedRegistration(string $action, mixed $user): bool
    {
        return $action === 'registration' && is_array($user) && ($user['status'] ?? null) === 'rejected';
    }

    private function isRejectedLogin(string $action, mixed $user): bool
    {
        return $action === 'login' && is_array($user) && ($user['status'] ?? null) === 'rejected';
    }

    private function buildSuccessfulLoginResponse(
        object $user,
        JWTTokenManagerInterface $jwtManager,
        string $processId,
        string $action
    ): JsonResponse {
        $token = $jwtManager->create($user);
        $response = new JsonResponse([
            'message' => 'Authentication success.',
        ]);

        $response->headers->setCookie($this->createJwtCookie($token));
        $this->resetUserLoginState($user);
        $this->logger->info('Frontend login poll succeeded', [
            'route' => 'user_login_check',
            'process' => $processId,
            'action' => $action,
            'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
            'user_email' => method_exists($user, 'getEmail') ? $user->getEmail() : null,
        ]);

        return $response;
    }

    private function buildSuccessfulRegistrationResponse(
        object $user,
        string $processId,
        string $action
    ): JsonResponse {
        $this->logger->info('Frontend registration poll succeeded', [
            'route' => 'user_login_check',
            'process' => $processId,
            'action' => $action,
            'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
            'user_email' => method_exists($user, 'getEmail') ? $user->getEmail() : null,
        ]);

        return new JsonResponse(['message' => 'Registration success.']);
    }

    private function buildRejectedRegistrationResponse(string $processId, string $action, ?string $reason): JsonResponse
    {
        $message = match ($reason) {
            'registration_rejected_duplicate_user' => 'Registration rejected: this email and public ID are already registered.',
            'registration_rejected_whitelist' => 'Registration rejected: this email address is not allowed.',
            default => 'Registration rejected.',
        };

        $this->logger->warning('Frontend registration poll detected rejected registration', [
            'route' => 'user_login_check',
            'process' => $processId,
            'action' => $action,
            'reason' => $reason,
        ]);

        return new JsonResponse(['message' => $message]);
    }

    private function buildRejectedLoginResponse(string $processId, string $action, ?string $reason): JsonResponse
    {
        $message = match ($reason) {
            'login_rejected_whitelist' => 'Authentication rejected: access has been revoked for this email address.',
            default => 'Authentication rejected.',
        };

        $this->logger->warning('Frontend login poll detected rejected authentication', [
            'route' => 'user_login_check',
            'process' => $processId,
            'action' => $action,
            'reason' => $reason,
        ]);

        return new JsonResponse(['message' => $message]);
    }

    private function buildFailedPollResponse(string $processId, string $action): JsonResponse
    {
        $this->logger->warning('Frontend login poll finished without successful authentication', [
            'route' => 'user_login_check',
            'process' => $processId,
            'action' => $action,
        ]);

        $message = $action === 'registration'
            ? 'Registration timed out. Please try again.'
            : 'Authentication timed out. Please try again.';

        return new JsonResponse(['message' => $message]);
    }

    private function createJwtCookie(string $token): Cookie
    {
        return new Cookie(
            'jwt_token',
            $token,
            time() + 3600,
            '/',
            null,
            false,
            true,
            false,
            'Strict'
        );
    }

    private function resetUserLoginState(object $user): void
    {
        $user->setAllowed(false);
        $user->setProcess(null);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function resolveLoginRequest(Request $request): array
    {
        $userPublicId = null;

        if ($request->query->has('userPublicId')) {
            $userPublicId = $request->query->get('userPublicId');

            // Debug log for raw userPublicId
            $this->logger->info('userPublicId_raw', [
                'route' => 'instance_login',
                'userPublicId' => $userPublicId,
                'userPublicId_length' => strlen($userPublicId),
            ]);

            if ($userPublicId === null) {
                $this->logger->error('Missing userPublicId value on login request', [
                    'route' => 'instance_login',
                    'exception' => BadRequestHttpException::class,
                ]);

                throw new BadRequestHttpException('Missing userPublicId value.');
            }

            if (strlen($userPublicId) < 42 || strlen($userPublicId) > 50) {
                $this->logger->error('Invalid userPublicId length on login request', [
                    'route' => 'instance_login',
                    'user_public_id_length' => strlen($userPublicId),
                    'exception' => \InvalidArgumentException::class,
                ]);

                throw new \InvalidArgumentException('Invalid length.');
            }

            if (!preg_match('/^[A-Za-z0-9+\/ ]+={0,2}$/', $userPublicId)) {
                $this->logger->error('Invalid userPublicId characters on login request', [
                    'route' => 'instance_login',
                    'exception' => \InvalidArgumentException::class,
                ]);

                throw new \InvalidArgumentException('Invalid characters.');
            }

            $decoded = base64_decode($userPublicId, true);

            if ($decoded === false || strlen($decoded) < 32 || strlen($decoded) > 40) {
                $this->logger->error('Invalid userPublicId payload on login request', [
                    'route' => 'instance_login',
                    'decoded_length' => $decoded === false ? null : strlen($decoded),
                    'exception' => \InvalidArgumentException::class,
                ]);

                throw new \InvalidArgumentException('Invalid token.');
            }
        }

        return [
            'userPublicId' => $userPublicId,
        ];
    }

    private function pollState(string $processId, string $action): array|\App\Entity\User
    {
        $startTime = time();
        $maxWait = $this->userRepository->count([]) === 0 ? 30 : 10;
        $response = [];

        $this->logger->info('Polling started for frontend state check', [
            'process' => $processId,
            'action' => $action,
            'max_wait_seconds' => $maxWait,
        ]);

        do {
            $user = $this->userRepository->findOneBy([
                'process' => $processId,
            ]);

            if ($action === 'login' && $user && $user->isAllowed()) {
                $response = $user;
                $this->logger->info('Polling found login-ready user', [
                    'process' => $processId,
                    'action' => $action,
                    'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                ]);
                break;
            }

            if ($action === 'registration' && $user) {
                $response = $user;
                $this->logger->info('Polling found registration-ready user', [
                    'process' => $processId,
                    'action' => $action,
                    'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                    'user_email' => method_exists($user, 'getEmail') ? $user->getEmail() : null,
                    'user_process' => method_exists($user, 'getProcess') ? $user->getProcess() : null,
                    'user_allowed' => method_exists($user, 'isAllowed') ? $user->isAllowed() : null,
                ]);
                break;
            }

            if ($action === 'registration' && $this->isRegistrationRejected($processId)) {
                $response = $this->buildRejectedRegistrationState($processId);
                break;
            }

            if ($action === 'login' && $this->isLoginRejected($processId)) {
                $response = $this->buildRejectedLoginState($processId);
                break;
            }

            if ((time() - $startTime) >= $maxWait) {
                $matchingUser = $this->userRepository->findOneBy([
                    'process' => $processId,
                ]);

                $this->logger->warning('Polling timed out without matching user state', [
                    'process' => $processId,
                    'action' => $action,
                    'max_wait_seconds' => $maxWait,
                    'matching_user_found' => $matchingUser !== null,
                    'matching_user_id' => $matchingUser !== null && method_exists($matchingUser, 'getId') ? $matchingUser->getId() : null,
                    'matching_user_email' => $matchingUser !== null && method_exists($matchingUser, 'getEmail') ? $matchingUser->getEmail() : null,
                    'matching_user_process' => $matchingUser !== null && method_exists($matchingUser, 'getProcess') ? $matchingUser->getProcess() : null,
                    'matching_user_allowed' => $matchingUser !== null && method_exists($matchingUser, 'isAllowed') ? $matchingUser->isAllowed() : null,
                    'registration_rejected' => $action === 'registration' ? $this->isRegistrationRejected($processId) : null,
                    'login_rejected' => $action === 'login' ? $this->isLoginRejected($processId) : null,
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

    private function buildRejectedRegistrationState(string $processId): array
    {
        $process = $this->processRepository->findRejectedRegistrationProcess($processId);

        if ($process === null) {
            return [];
        }

        $this->logger->info('Detected rejected registration process during polling', [
            'process' => $processId,
            'reason' => $process->getAuthId(),
        ]);

        return [
            'status' => 'rejected',
            'reason' => $process->getAuthId(),
        ];
    }

    private function buildRejectedLoginState(string $processId): array
    {
        $process = $this->processRepository->findRejectedLoginProcess($processId);

        if ($process === null) {
            return [];
        }

        $this->logger->info('Detected rejected login process during polling', [
            'process' => $processId,
            'reason' => $process->getAuthId(),
        ]);

        return [
            'status' => 'rejected',
            'reason' => $process->getAuthId(),
        ];
    }
}