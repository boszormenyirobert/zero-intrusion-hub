<?php

namespace App\Service\Account\HUB;

use App\Repository\UserRepository;
use App\Service\Instance\HUB\JwtContextService;
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
        private JwtContextService $jwtContextService,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {}

    public function resolveAccountContext(Request $request): ?array
    {
        $jwtContext = $this->jwtContextService->build($request);

        if (!$jwtContext['isJwtValid']) {
            $this->logger->warning('Account access denied due to invalid JWT or unknown user', [
                'route' => $request->attributes->get('_route'),
                'is_jwt_valid' => $jwtContext['isJwtValid'],
            ]);

            return null;
        }

        $user = $this->findUserFromToken($jwtContext['payload']);

        if ($user === null) {
            $this->logger->warning('Account access denied due to invalid JWT or unknown user', [
                'route' => $request->attributes->get('_route'),
                'is_jwt_valid' => $jwtContext['isJwtValid'],
            ]);

            return null;
        }

        $this->logger->info('Account user identified from JWT', [
            'route' => $request->attributes->get('_route'),
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

        $this->logger->info('Loading business subscription for account view', [
            'process' => $process,
            'user_public_id' => $user['publicId'] ?? null,
            'user_email' => $user['email'] ?? null,
        ]);

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

        $this->logger->info('Account business subscription payload loaded', [
            'process' => $process,
            'accounts_count' => count($businessSubscription['accounts'] ?? []),
            'subscription_keys' => array_keys($businessSubscription['businessSubscription'] ?? []),
        ]);

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

    private function findUserFromToken(?array $jwtToken): ?array
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