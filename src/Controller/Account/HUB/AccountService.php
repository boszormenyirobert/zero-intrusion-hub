<?php

namespace App\Controller\Account\HUB;

use App\Repository\UserRepository;
use App\Service\JWT\JwtService;
use App\Service\User\UserRegistrationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountService
{
    private const PILLS = [
        'pswManager' => 'Password Manager',
        'biometric' => 'Secure biometric',
        'basic' => 'Business Basic',
        'plus' => 'Business Plus',
        'pro' => 'Business Pro',
    ];

    public function __construct(
        private JwtService $jwtService,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {}

    public function resolveAccountContext(Request $request): ?array
    {
        $jwtContext = $this->buildJwtContext($request);

        if (!$jwtContext['isJwtValid']) {
            $this->logger->warning('Account access denied due to invalid JWT or unknown user', [
                'route' => 'account',
                'is_jwt_valid' => $jwtContext['isJwtValid'],
            ]);

            return null;
        }

        $user = $this->findUserFromToken($jwtContext['payload']);

        if ($user === null) {
            $this->logger->warning('Account access denied due to invalid JWT or unknown user', [
                'route' => 'account',
                'is_jwt_valid' => $jwtContext['isJwtValid'],
            ]);

            return null;
        }

        $this->logger->info('Account user identified from JWT', [
            'route' => 'account',
            'user_public_id' => $user['publicId'] ?? null,
            'user_email' => $user['email'] ?? null,
        ]);

        return [
            'jwt' => $jwtContext,
            'user' => $user,
        ];
    }

    public function loadBusinessSubscription(
        UserRegistrationService $userRegistrationService,
        array $user
    ): array {
        $process = 'get_registrated_business';

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration([
            $process => $user,
        ]);

        $businessSubscription = \json_decode($response->getContent(), true);

        if (!$businessSubscription || !isset($businessSubscription['businessSubscription'])) {
            $this->logger->warning('Account backend response missing expected subscription payload', [
                'route' => 'account',
                'process' => $process,
            ]);

            return [
                'accounts' => [],
                'businessSubscription' => [],
            ];
        }

        return $businessSubscription;
    }

    public function buildAccountViewData(
        array $accountContext,
        array $businessSubscription,
        bool $menuItemInstanceRegistration
    ): array {
        return [
            'is_jwt_valid' => $accountContext['jwt']['isJwtValid'],
            'user' => [
                'userPublicId' => $accountContext['jwt']['userPublicId'],
                'userEmail' => $accountContext['jwt']['userEmail'],
            ],
            'accounts' => $businessSubscription['accounts'],
            'businessSubscription' => $this->getSelectedSubscription($businessSubscription['businessSubscription']),
            'menuItem_instanceRegistration' => $menuItemInstanceRegistration,
            'pills' => self::PILLS,
        ];
    }

    private function buildJwtContext(Request $request): array
    {
        $token = $request->cookies->get('jwt_token');

        if (!$token) {
            $this->logger->warning('Account page accessed without JWT cookie', [
                'route' => 'account',
            ]);

            return [
                'isJwtValid' => false,
                'userPublicId' => '',
                'userEmail' => '',
                'payload' => null,
            ];
        }

        $payload = $this->jwtService->jwtValidation($token);
        $isJwtValid = $payload !== null;
        $userPublicId = $isJwtValid ? ($payload['publicId'] ?? '') : '';
        $userEmail = $isJwtValid ? ($payload['username'] ?? '') : '';

        $this->logger->info('Account page JWT evaluated', [
            'route' => 'account',
            'is_jwt_valid' => $isJwtValid,
            'user_public_id' => $userPublicId,
            'user_email' => $userEmail,
        ]);

        return [
            'isJwtValid' => $isJwtValid,
            'userPublicId' => $userPublicId,
            'userEmail' => $userEmail,
            'payload' => $payload,
        ];
    }

    private function findUserFromToken(array $jwtToken): ?array
    {
        $email = $jwtToken['username'] ?? null;

        if (!$email) {
            $this->logger->warning('JWT token missing username during account user identification');

            return null;
        }

        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);

        if (!$user) {
            $this->logger->warning('No account user found for JWT username', [
                'username' => $email,
            ]);

            return null;
        }

        return [
            'publicId' => $user->getPublicId(),
            'email' => $user->getEmail(),
        ];
    }

    private function getSelectedSubscription(array $businessSubscription): ?array
    {
        foreach ($businessSubscription as $key => $value) {
            if ($value === true) {
                $subscription['subscription'] = $key;
                $subscription['id'] = $businessSubscription['id'];

                $this->logger->info('Selected active business subscription resolved', [
                    'subscription' => $key,
                    'subscription_id' => $businessSubscription['id'] ?? null,
                ]);

                return $subscription;
            }
        }

        $this->logger->warning('No active business subscription found in payload');

        return null;
    }
}