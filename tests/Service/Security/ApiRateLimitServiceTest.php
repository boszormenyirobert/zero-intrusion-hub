<?php

declare(strict_types=1);

namespace App\Tests\Service\Security;

use App\Service\Security\ApiRateLimitService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class ApiRateLimitServiceTest extends TestCase
{
    public function testUserLoginLimiterAllowsRequestAndLogsDecision(): void
    {
        $request = Request::create('/api/user-login', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $request->attributes->set('_route', 'api_instance_login');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'API rate limit accepted',
                self::callback(static function (array $context): bool {
                    return $context['limiter'] === 'api_user_login'
                        && $context['route'] === 'api_instance_login'
                        && $context['path'] === '/api/user-login'
                        && $context['method'] === 'POST'
                        && $context['client_ip_hash'] !== null
                        && array_key_exists('remaining_tokens', $context)
                        && array_key_exists('retry_after', $context);
                })
            );
        $logger
            ->expects(self::never())
            ->method('warning');

        $service = $this->createService($logger, 2, 2, 2, 2, 2, 2);

        self::assertNull($service->assertApiUserLoginAllowed($request));
    }

    public function testUserLoginLimiterReturnsJsonResponseWhenLimitExceededAndLogsDecision(): void
    {
        $request = Request::create('/api/user-login', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $request->attributes->set('_route', 'api_instance_login');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info');
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'API rate limit exceeded',
                self::callback(static function (array $context): bool {
                    return $context['limiter'] === 'api_user_login'
                        && $context['route'] === 'api_instance_login'
                        && $context['path'] === '/api/user-login'
                        && $context['method'] === 'POST'
                        && $context['client_ip_hash'] !== null
                        && $context['retry_after_seconds'] >= 1;
                })
            );

        $service = $this->createService($logger, 1, 2, 2, 2, 2, 2);

        self::assertNull($service->assertApiUserLoginAllowed($request));

        $response = $service->assertApiUserLoginAllowed($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame(['error' => 'Too many requests'], json_decode((string) $response->getContent(), true));
        self::assertTrue($response->headers->has('Retry-After'));
    }

    public function testUserLoginCallbackLimiterReturnsJsonResponseWhenLimitExceeded(): void
    {
        $request = Request::create('/api/user-login/callback', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $request->attributes->set('_route', 'user_login_callback');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'API rate limit exceeded',
                self::callback(static function (array $context): bool {
                    return $context['limiter'] === 'api_user_login_callback'
                        && $context['route'] === 'user_login_callback'
                        && $context['path'] === '/api/user-login/callback';
                })
            );

        $service = $this->createService($logger, 2, 2, 2, 2, 1, 2);

        self::assertNull($service->assertApiUserLoginCallbackAllowed($request));

        $response = $service->assertApiUserLoginCallbackAllowed($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(429, $response->getStatusCode());
    }

    public function testUserRegistrationCallbackLimiterReturnsJsonResponseWhenLimitExceeded(): void
    {
        $request = Request::create('/api/registration/callback', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $request->attributes->set('_route', 'user_registration_callback');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'API rate limit exceeded',
                self::callback(static function (array $context): bool {
                    return $context['limiter'] === 'api_user_registration_callback'
                        && $context['route'] === 'user_registration_callback'
                        && $context['path'] === '/api/registration/callback';
                })
            );

        $service = $this->createService($logger, 2, 2, 2, 2, 2, 1);

        self::assertNull($service->assertApiUserRegistrationCallbackAllowed($request));

        $response = $service->assertApiUserRegistrationCallbackAllowed($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(429, $response->getStatusCode());
    }

    private function createService(
        LoggerInterface $logger,
        int $loginLimit,
        int $newQrLimit,
        int $checkLimit,
        int $registrationLimit,
        int $loginCallbackLimit,
        int $registrationCallbackLimit
    ): ApiRateLimitService {
        return new ApiRateLimitService(
            $this->createFactory('api_user_login', $loginLimit),
            $this->createFactory('api_user_login_new_qr', $newQrLimit),
            $this->createFactory('api_user_login_check', $checkLimit),
            $this->createFactory('api_user_registration', $registrationLimit),
            $this->createFactory('api_user_login_callback', $loginCallbackLimit),
            $this->createFactory('api_user_registration_callback', $registrationCallbackLimit),
            $logger
        );
    }

    private function createFactory(string $id, int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => $id,
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => '1 minute',
        ], new InMemoryStorage());
    }
}