<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Logger\LogTrace;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Protects public authentication endpoints from brute-force, flood and
 * resource-abuse traffic.
 *
 * Behaviour:
 * - each incoming request consumes one token from the configured limiter
 * - if tokens are still available, the request is allowed to continue
 * - if the limit is exceeded, the request is rejected with HTTP 429
 * - the response includes a Retry-After header so the client knows when to retry
 *
 * Logging:
 * - accepted requests are logged with "API rate limit accepted"
 * - rejected requests are logged with "API rate limit exceeded"
 */
class ApiRateLimitService
{
    public function __construct(
        #[Autowire(service: 'limiter.api_user_login')]
        private RateLimiterFactory $apiUserLoginLimiter,
        #[Autowire(service: 'limiter.api_user_login_new_qr')]
        private RateLimiterFactory $apiUserLoginNewQrLimiter,
        #[Autowire(service: 'limiter.api_user_login_check')]
        private RateLimiterFactory $apiUserLoginCheckLimiter,
        #[Autowire(service: 'limiter.api_user_registration')]
        private RateLimiterFactory $apiUserRegistrationLimiter,
        #[Autowire(service: 'limiter.api_user_login_callback')]
        private RateLimiterFactory $apiUserLoginCallbackLimiter,
        #[Autowire(service: 'limiter.api_user_registration_callback')]
        private RateLimiterFactory $apiUserRegistrationCallbackLimiter,
        private LoggerInterface $logger,
    ) {
    }

    public function assertApiUserLoginAllowed(Request $request): ?JsonResponse
    {
        // Login QR creation is public, so repeated requests must be throttled.
        return $this->consume($this->apiUserLoginLimiter, 'api_user_login', $request);
    }

    public function assertApiUserLoginNewQrAllowed(Request $request): ?JsonResponse
    {
        // New QR generation is public and can otherwise be used for QR spam.
        return $this->consume($this->apiUserLoginNewQrLimiter, 'api_user_login_new_qr', $request);
    }

    public function assertApiUserLoginCheckAllowed(Request $request): ?JsonResponse
    {
        // Poll/check is intentionally called often, but still needs a ceiling.
        return $this->consume($this->apiUserLoginCheckLimiter, 'api_user_login_check', $request);
    }

    public function assertApiUserRegistrationAllowed(Request $request): ?JsonResponse
    {
        // Registration start is public and should be protected against flooding.
        return $this->consume($this->apiUserRegistrationLimiter, 'api_user_registration', $request);
    }

    public function assertApiUserLoginCallbackAllowed(Request $request): ?JsonResponse
    {
        // Public login callbacks should be protected against callback flooding.
        return $this->consume($this->apiUserLoginCallbackLimiter, 'api_user_login_callback', $request);
    }

    public function assertApiUserRegistrationCallbackAllowed(Request $request): ?JsonResponse
    {
        // Public registration callbacks should be protected against callback flooding.
        return $this->consume($this->apiUserRegistrationCallbackLimiter, 'api_user_registration_callback', $request);
    }

    private function consume(RateLimiterFactory $factory, string $limiterName, Request $request): ?JsonResponse
    {
        // The current implementation groups requests by client IP address.
        $clientIp = $request->getClientIp() ?? 'unknown';
        $limit = $factory->create($clientIp)->consume(1);

        $context = [
            'limiter' => $limiterName,
            'route' => $request->attributes->get('_route'),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'client_ip_hash' => LogTrace::fingerprint($clientIp),
            'remaining_tokens' => $limit->getRemainingTokens(),
            'limit' => $limit->getLimit(),
            'retry_after' => $limit->getRetryAfter()->format(DATE_ATOM),
        ];

        if ($limit->isAccepted()) {
            $this->logger->info('API rate limit accepted', $context);

            return null;
        }

        $retryAfterSeconds = max(1, $limit->getRetryAfter()->getTimestamp() - time());

        $this->logger->warning('API rate limit exceeded', [
            ...$context,
            'retry_after_seconds' => $retryAfterSeconds,
        ]);

        return new JsonResponse([
            'error' => 'Too many requests',
        ], 429, [
            'Retry-After' => (string) $retryAfterSeconds,
        ]);
    }
}
