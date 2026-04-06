<?php

namespace App\Controller\User\Login\HUB;

use App\Controller\User\UserService;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginService
{
    public function __construct(
        private LoggerInterface $logger,
        private UserService $userService,
        private EntityManagerInterface $entityManager
    ) {}

    public function buildLoginViewData(Request $request, bool $menuItemInstanceRegistration): ?array
    {
        $this->logger->info('HUB login page requested', [
            'route' => 'instance_login',
            'user_public_id_present' => $request->query->has('userPublicId'),
        ]);

        $loginRequest = $this->resolveLoginRequest($request);

        if ($loginRequest['shouldAbort']) {
            return null;
        }

        $response = $this->userService->getQrCode('user_login', [], $loginRequest['userPublicId']);

        $this->logger->info('Login QR code generated', [
            'route' => 'instance_login',
            'process' => 'user_login',
            'domain_process_id' => $response['domainProcessId'] ?? null,
            'user_public_id_present' => $loginRequest['userPublicId'] !== null,
        ]);

        return [
            'menuItem_instanceRegistration' => $menuItemInstanceRegistration,
            'processId' => $response['domainProcessId'],
            'qrCodeData' => $response,
            'qrCode' => $response['qrCode'],
            'user' => [],
            'action' => 'login',
        ];
    }

    public function buildFrontendPollResponse(
        Request $request,
        UserRepository $userRepository,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $pollRequest = $this->buildPollRequest($request);
        $user = $this->pollState($pollRequest['processId'], $userRepository, $pollRequest['action']);

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

    private function buildPollRequest(Request $request): array
    {
        $processId = trim($request->query->get('processId'));
        $action = trim($request->query->get('action'));

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

    private function buildFailedPollResponse(string $processId, string $action): JsonResponse
    {
        $this->logger->warning('Frontend login poll finished without successful authentication', [
            'route' => 'user_login_check',
            'process' => $processId,
            'action' => $action,
        ]);

        return new JsonResponse(['message' => 'Unsuccess authentication.']);
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

            if ($userPublicId === null) {
                $this->logger->warning('Missing userPublicId value on login request', [
                    'route' => 'instance_login',
                ]);

                return [
                    'shouldAbort' => true,
                    'userPublicId' => null,
                ];
            }

            if (strlen($userPublicId) !== 48) {
                $this->logger->warning('Invalid userPublicId length on login request', [
                    'route' => 'instance_login',
                    'user_public_id_length' => strlen($userPublicId),
                ]);

                throw new \InvalidArgumentException('Invalid length.');
            }

            if (!preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $userPublicId)) {
                $this->logger->warning('Invalid userPublicId characters on login request', [
                    'route' => 'instance_login',
                ]);

                throw new \InvalidArgumentException('Invalid characters.');
            }

            $decoded = base64_decode($userPublicId, true);

            if ($decoded === false || strlen($decoded) !== 35) {
                $this->logger->warning('Invalid userPublicId payload on login request', [
                    'route' => 'instance_login',
                    'decoded_length' => $decoded === false ? null : strlen($decoded),
                ]);

                throw new \InvalidArgumentException('Invalid token.');
            }
        }

        return [
            'shouldAbort' => false,
            'userPublicId' => $userPublicId,
        ];
    }

    private function pollState(string $processId, UserRepository $userRepository, string $action): array|\App\Entity\User
    {
        $startTime = time();
        $maxWait = 10;
        $response = [];

        do {
            $user = $userRepository->findOneBy([
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
                ]);
                break;
            }

            if ((time() - $startTime) >= $maxWait) {
                $this->logger->warning('Polling timed out without matching user state', [
                    'process' => $processId,
                    'action' => $action,
                    'max_wait_seconds' => $maxWait,
                ]);
                break;
            }

            usleep(500000);
        } while (true);

        return $response;
    }
}