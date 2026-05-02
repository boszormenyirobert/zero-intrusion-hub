<?php

namespace App\Service\Account\HUB;

use App\DTO\AccountContextDTO;
use App\DTO\AccountViewDataDTO;
use App\DTO\AuthenticatedUserDTO;
use App\DTO\BusinessSubscriptionDataDTO;
use App\DTO\SelectedSubscriptionDTO;
use App\Logger\LogTrace;
use App\Repository\UserRepository;
use App\Service\Instance\HUB\JwtContextService;
use App\Service\Shared\ProcessKey;
use App\Service\User\BackendForwardingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountService
{
    private const ROUTE_ACCOUNT = 'account';
    private const JWT_USERNAME_KEY = 'username';
    private const RESPONSE_KEY_BUSINESS_SUBSCRIPTION = 'businessSubscription';
    private const RESPONSE_KEY_ACCOUNTS = 'accounts';
    private const RESPONSE_KEY_ID = 'id';
    private const PROCESS_GET_REGISTRATED_BUSINESS = ProcessKey::GET_REGISTRATED_BUSINESS;

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
    ) {
    }

    public function resolveAccountContext(Request $request): ?AccountContextDTO
    {
        $jwtContext = $this->jwtContextService->build($request);

        $user = $this->findUserFromToken($jwtContext->payload);

        if ($user === null) {
            $this->logAccountAccessDenied($request, $jwtContext->isJwtValid);
            return null;
        }

        $this->logger->info('Account user identified from JWT', [
            'route' => $request->attributes->get('_route'),
            'user_public_id_hash' => LogTrace::fingerprint($user->publicId),
            'user_email_hash' => LogTrace::fingerprint($user->email),
        ]);

        return new AccountContextDTO($jwtContext, $user);
    }

    public function loadBusinessSubscription(
        BackendForwardingService $backendForwardingService,
        AuthenticatedUserDTO $user
    ): BusinessSubscriptionDataDTO {
        $process = self::PROCESS_GET_REGISTRATED_BUSINESS;

        $this->logger->info('Loading business subscription for account view', [
            'process' => $process,
            'user_public_id_hash' => LogTrace::fingerprint($user->publicId),
            'user_email_hash' => LogTrace::fingerprint($user->email),
        ]);

        /** @var Response $response */
        $response = $backendForwardingService->forwardRegistration([
            $process => $user->toArray(),
        ]);

        $businessSubscription = $this->decodeBusinessSubscriptionPayload($response, $process);

        $this->logger->info('Account business subscription payload loaded', [
            'process' => $process,
            'accounts_count' => count($businessSubscription[self::RESPONSE_KEY_ACCOUNTS] ?? []),
            'subscription_keys' => array_keys($businessSubscription[self::RESPONSE_KEY_BUSINESS_SUBSCRIPTION] ?? []),
        ]);

        return BusinessSubscriptionDataDTO::fromArray($businessSubscription);
    }

    public function buildAccountViewData(
        AccountContextDTO $accountContext,
        BusinessSubscriptionDataDTO $businessSubscription,
        bool $menuItemInstanceRegistration
    ): AccountViewDataDTO {
        return new AccountViewDataDTO(
            $accountContext->jwt->isJwtValid,
            $accountContext->jwt->toUserDto(),
            $businessSubscription->accounts,
            $this->getSelectedSubscription($businessSubscription->businessSubscription),
            $menuItemInstanceRegistration,
            self::PILLS,
        );
    }

    private function findUserFromToken(?array $jwtToken): ?AuthenticatedUserDTO
    {
        $email = $jwtToken[self::JWT_USERNAME_KEY] ?? null;

        if (!$email) {
            $this->logger->warning('JWT token missing username during account user identification');

            return null;
        }

        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);

        if (!$user) {
            $this->logger->warning('No account user found for JWT username', [
                'username_hash' => LogTrace::fingerprint($email),
            ]);

            return null;
        }

        return new AuthenticatedUserDTO(
            $user->getPublicId(),
            $user->getEmail(),
        );
    }

    private function getSelectedSubscription(array $businessSubscription): ?SelectedSubscriptionDTO
    {
        foreach ($businessSubscription as $key => $value) {
            if ($value === true) {
                $subscription = new SelectedSubscriptionDTO(
                    $businessSubscription[self::RESPONSE_KEY_ID] ?? null,
                    $key,
                );

                $this->logger->info('Selected active business subscription resolved', [
                    'subscription' => $key,
                    'subscription_id' => $businessSubscription[self::RESPONSE_KEY_ID] ?? null,
                ]);

                return $subscription;
            }
        }

        $this->logger->warning('No active business subscription found in payload');

        return null;
    }

    private function decodeBusinessSubscriptionPayload(Response $response, string $process): array
    {
        $decoded = $this->decodeBackendJson($response, $process);

        if ($decoded === [] || !isset($decoded[self::RESPONSE_KEY_BUSINESS_SUBSCRIPTION])) {
            $this->logger->warning('Account backend response missing expected subscription payload', [
                'route' => self::ROUTE_ACCOUNT,
                'process' => $process,
            ]);

            return [];
        }

        return $decoded;
    }

    private function decodeBackendJson(Response $response, string $process): array
    {
        try {
            $decoded = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('Account backend response could not be decoded', [
                'route' => self::ROUTE_ACCOUNT,
                'process' => $process,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function logAccountAccessDenied(Request $request, bool $isJwtValid): void
    {
        $this->logger->warning('Account access denied due to invalid JWT or unknown user', [
            'route' => $request->attributes->get('_route'),
            'is_jwt_valid' => $isJwtValid,
        ]);
    }
}
