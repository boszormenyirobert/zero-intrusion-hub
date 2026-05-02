<?php

declare(strict_types=1);

namespace App\Tests\Controller\User\Registration\Api;

use App\Attribute\ClientAuthRequired;
use App\Attribute\PublicRoute;
use App\Controller\User\Registration\Api\RegistrationController;
use App\DTO\QrCodeResponseDTO;
use App\Service\Security\ApiRateLimitService;
use App\Service\User\Callback\UserProcessCallbackService;
use App\Service\User\Registration\Api\RegistrationApiRequestMapper;
use App\Service\User\UserQrCodeService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RegistrationControllerTest extends TestCase
{
    public function testApiUserRegistrationRouteRequiresClientAuthAttribute(): void
    {
        $reflectionMethod = new \ReflectionMethod(RegistrationController::class, 'apiUserRegistration');

        self::assertNotEmpty($reflectionMethod->getAttributes(ClientAuthRequired::class));
    }

    public function testSystemHubRegistrationCallbackRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(RegistrationController::class, 'systemHubRegistrationCallback');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testApiUserRegistrationReturnsUnauthorizedWhenHeaderMissing(): void
    {
        $controller = $this->createController();
        $request = Request::create('/api/user-registration', 'POST', content: '{}');

        $response = $controller->apiUserRegistration($request);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Missing X-Client-Auth header!'], json_decode((string) $response->getContent(), true));
    }

    public function testApiUserRegistrationDelegatesToQrCodeService(): void
    {
        $request = Request::create('/api/user-registration', 'POST', content: json_encode([
            'publicId' => 'corporate-id',
            'domain' => 'https://example.test',
        ], JSON_THROW_ON_ERROR));
        $request->headers->set('x-client-auth', 'header-value');

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::once())
            ->method('getQrCode')
            ->with('user_registration', self::callback(static function ($dto): bool {
                return $dto->publicId === 'corporate-id'
                    && $dto->domain === 'https://example.test'
                    && $dto->hmac === 'header-value';
            }))
            ->willReturn(QrCodeResponseDTO::fromArray([
                'registrationProcessId' => 'registration-123',
                'qrCode' => 'qr-content',
            ]));

        $controller = $this->createController(qrCodeService: $qrCodeService);
        $response = $controller->apiUserRegistration($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('registration-123', json_decode((string) $response->getContent(), true)['registrationProcessId']);
    }

    public function testApiUserRegistrationUsesValidatedRequestAttributeWhenHeaderWasResolvedUpstream(): void
    {
        $request = Request::create('/api/user-registration', 'POST', content: json_encode([
            'publicId' => 'corporate-id',
            'domain' => 'https://example.test',
        ], JSON_THROW_ON_ERROR));
        $request->attributes->set('_client_auth_header', 'validated-upstream-header');

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::once())
            ->method('getQrCode')
            ->with('user_registration', self::callback(static function ($dto): bool {
                return $dto->publicId === 'corporate-id'
                    && $dto->domain === 'https://example.test'
                    && $dto->hmac === 'validated-upstream-header';
            }))
            ->willReturn(QrCodeResponseDTO::fromArray([
                'registrationProcessId' => 'registration-123',
                'qrCode' => 'qr-content',
            ]));

        $controller = $this->createController(qrCodeService: $qrCodeService);
        $response = $controller->apiUserRegistration($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('registration-123', json_decode((string) $response->getContent(), true)['registrationProcessId']);
    }

    public function testApiUserRegistrationReturnsTooManyRequestsWhenRateLimitIsExceeded(): void
    {
        $request = Request::create('/api/user-registration', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);

        $rateLimitService = $this->createRateLimitService(registrationLimit: 1);
        self::assertNull($rateLimitService->assertApiUserRegistrationAllowed($request));

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::never())
            ->method('getQrCode');

        $controller = $this->createController(rateLimitService: $rateLimitService, qrCodeService: $qrCodeService);
        $response = $controller->apiUserRegistration($request);

        self::assertSame(429, $response->getStatusCode());
        self::assertSame(['error' => 'Too many requests'], json_decode((string) $response->getContent(), true));
    }

    public function testSystemHubRegistrationCallbackReturnsForbiddenWhenRejected(): void
    {
        $request = Request::create('/api/registration/callback', 'POST', content: json_encode([
            'signature' => 'signature',
            'publicId' => 'public-id',
            'email' => 'user@example.test',
            'registrationProcessId' => 'registration-123',
        ], JSON_THROW_ON_ERROR));

        $callbackService = $this->createMock(UserProcessCallbackService::class);
        $callbackService
            ->expects(self::once())
            ->method('createRegisteredUser')
            ->with(self::callback(static fn ($dto): bool => $dto->getProcessId() === 'registration-123'))
            ->willReturn(false);

        $controller = $this->createController(callbackService: $callbackService);
        $response = $controller->systemHubRegistrationCallback($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['status' => 'error', 'message' => 'Registration rejected.'], json_decode((string) $response->getContent(), true));
    }

    public function testSystemHubRegistrationCallbackReturnsSuccessWhenAccepted(): void
    {
        $request = Request::create('/api/registration/callback', 'POST', content: json_encode([
            'signature' => 'signature',
            'publicId' => 'public-id',
            'email' => 'user@example.test',
            'registrationProcessId' => 'registration-123',
        ], JSON_THROW_ON_ERROR));

        $callbackService = $this->createMock(UserProcessCallbackService::class);
        $callbackService
            ->expects(self::once())
            ->method('createRegisteredUser')
            ->willReturn(true);

        $controller = $this->createController(callbackService: $callbackService);
        $response = $controller->systemHubRegistrationCallback($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'success', 'data' => 'callback success'], json_decode((string) $response->getContent(), true));
    }

    public function testSystemHubRegistrationCallbackReturnsTooManyRequestsWhenRateLimitIsExceeded(): void
    {
        $request = Request::create('/api/registration/callback', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);

        $rateLimitService = $this->createRateLimitService(registrationCallbackLimit: 1);
        self::assertNull($rateLimitService->assertApiUserRegistrationCallbackAllowed($request));

        $callbackService = $this->createMock(UserProcessCallbackService::class);
        $callbackService
            ->expects(self::never())
            ->method('createRegisteredUser');

        $controller = $this->createController(rateLimitService: $rateLimitService, callbackService: $callbackService);
        $response = $controller->systemHubRegistrationCallback($request);

        self::assertSame(429, $response->getStatusCode());
        self::assertSame(['error' => 'Too many requests'], json_decode((string) $response->getContent(), true));
    }

    public function testSystemHubRegistrationCallbackReturnsBadRequestForInvalidJson(): void
    {
        $request = Request::create('/api/registration/callback', 'POST', content: '{invalid');
        $controller = $this->createController();

        $response = $controller->systemHubRegistrationCallback($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertArrayHasKey('message', json_decode((string) $response->getContent(), true));
    }

    public function testSystemHubRegistrationCallbackSanitizesUnexpectedServerErrors(): void
    {
        $request = Request::create('/api/registration/callback', 'POST', content: json_encode([
            'signature' => 'signature',
            'publicId' => 'public-id',
            'email' => 'user@example.test',
            'registrationProcessId' => 'registration-123',
        ], JSON_THROW_ON_ERROR));

        $callbackService = $this->createMock(UserProcessCallbackService::class);
        $callbackService
            ->expects(self::once())
            ->method('createRegisteredUser')
            ->willThrowException(new \RuntimeException('database password leaked'));

        $controller = $this->createController(callbackService: $callbackService);
        $response = $controller->systemHubRegistrationCallback($request);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame([
            'status' => 'error',
            'message' => 'Internal Server Error',
        ], json_decode((string) $response->getContent(), true));
    }

    private function createController(
        ?LoggerInterface $logger = null,
        ?UserQrCodeService $qrCodeService = null,
        ?UserProcessCallbackService $callbackService = null,
        ?RegistrationApiRequestMapper $requestMapper = null,
        ?ApiRateLimitService $rateLimitService = null
    ): RegistrationController {
        return new RegistrationController(
            $logger ?? $this->createMock(LoggerInterface::class),
            $qrCodeService ?? $this->createMock(UserQrCodeService::class),
            $callbackService ?? $this->createMock(UserProcessCallbackService::class),
            $requestMapper ?? new RegistrationApiRequestMapper($logger ?? $this->createMock(LoggerInterface::class)),
            $rateLimitService ?? $this->createRateLimitService()
        );
    }

    private function createRateLimitService(
        int $loginLimit = 100,
        int $newQrLimit = 100,
        int $checkLimit = 100,
        int $registrationLimit = 100,
        int $loginCallbackLimit = 100,
        int $registrationCallbackLimit = 100
    ): ApiRateLimitService {
        return new ApiRateLimitService(
            $this->createFactory('api_user_login', $loginLimit),
            $this->createFactory('api_user_login_new_qr', $newQrLimit),
            $this->createFactory('api_user_login_check', $checkLimit),
            $this->createFactory('api_user_registration', $registrationLimit),
            $this->createFactory('api_user_login_callback', $loginCallbackLimit),
            $this->createFactory('api_user_registration_callback', $registrationCallbackLimit),
            $this->createMock(LoggerInterface::class)
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
