<?php

declare(strict_types=1);

namespace App\Tests\Controller\User\Login\Api;

use App\Attribute\CsrfProtectedRoute;
use App\Attribute\PublicRoute;
use App\Controller\User\Login\Api\LoginController;
use App\DTO\QrCodeResponseDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\JWT\JwtService;
use App\Service\Security\ApiRateLimitService;
use App\Service\User\Login\Api\LoginApiRequestMapper;
use App\Service\User\Callback\UserProcessCallbackService;
use App\Service\User\UserQrCodeService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class LoginControllerTest extends TestCase
{
    public function testApiLoginRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(LoginController::class, 'apiLogin');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testSystemHubLoginCallbackRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(LoginController::class, 'systemHubLoginCallback');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testUserLoginNewQrRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(LoginController::class, 'userLoginNewQr');
        $csrfAttribute = $reflectionMethod->getAttributes(CsrfProtectedRoute::class)[0]->newInstance();

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(CsrfProtectedRoute::class));
        self::assertSame('userLoginCsrf', $csrfAttribute->tokenId);
        self::assertSame('X-CSRF-TOKEN', $csrfAttribute->tokenField);
        self::assertSame('header', $csrfAttribute->tokenSource);
        self::assertSame('Invalid CSRF token', $csrfAttribute->failureMessage);
    }

    public function testUserLoginCheckRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(LoginController::class, 'userLoginCheck');
        $csrfAttribute = $reflectionMethod->getAttributes(CsrfProtectedRoute::class)[0]->newInstance();

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(CsrfProtectedRoute::class));
        self::assertSame('userLoginCsrf', $csrfAttribute->tokenId);
        self::assertSame('X-CSRF-TOKEN', $csrfAttribute->tokenField);
        self::assertSame('header', $csrfAttribute->tokenSource);
        self::assertSame('Invalid CSRF token', $csrfAttribute->failureMessage);
    }

    public function testApiLoginReturnsQrPayload(): void
    {
        $request = Request::create('/api/user-login', 'POST', content: json_encode([
            'publicId' => 'corp-id',
            'domain' => 'https://example.test',
            'userPublicId' => 'user-123',
        ], JSON_THROW_ON_ERROR));

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::once())
            ->method('getQrCode')
            ->willReturn(QrCodeResponseDTO::fromArray([
                'domainProcessId' => 'process-123',
                'qrCode' => 'qr-content',
            ]));

        $controller = $this->createController(qrCodeService: $qrCodeService);
        $response = $controller->apiLogin($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('process-123', json_decode((string) $response->getContent(), true)['domainProcessId']);
    }

    public function testApiLoginReturnsTooManyRequestsWhenRateLimitIsExceeded(): void
    {
        $request = Request::create('/api/user-login', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);

        $rateLimitService = $this->createRateLimitService(loginLimit: 1);
        self::assertNull($rateLimitService->assertApiUserLoginAllowed($request));

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::never())
            ->method('getQrCode');

        $controller = $this->createController(rateLimitService: $rateLimitService, qrCodeService: $qrCodeService);
        $response = $controller->apiLogin($request);

        self::assertSame(429, $response->getStatusCode());
        self::assertSame(['error' => 'Too many requests'], json_decode((string) $response->getContent(), true));
    }

    public function testSystemHubLoginCallbackReturnsSuccessState(): void
    {
        $request = Request::create('/api/user-login/callback', 'POST', content: json_encode([
            'signature' => 'signature',
            'publicId' => 'public-id',
            'email' => 'user@example.test',
            'processId' => 'process-123',
        ], JSON_THROW_ON_ERROR));

        $callbackService = $this->createMock(UserProcessCallbackService::class);
        $callbackService
            ->expects(self::once())
            ->method('allowLoginProcess')
            ->with(self::callback(static fn ($dto): bool => $dto->getEmail() === 'user@example.test' && $dto->getProcessId() === 'process-123'))
            ->willReturn(true);

        $controller = $this->createController(callbackService: $callbackService);
        $response = $controller->systemHubLoginCallback($request);

        self::assertSame(['status' => 'ok', 'success' => true], json_decode((string) $response->getContent(), true));
    }

    public function testSystemHubLoginCallbackReturnsTooManyRequestsWhenRateLimitIsExceeded(): void
    {
        $request = Request::create('/api/user-login/callback', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);

        $rateLimitService = $this->createRateLimitService(loginCallbackLimit: 1);
        self::assertNull($rateLimitService->assertApiUserLoginCallbackAllowed($request));

        $callbackService = $this->createMock(UserProcessCallbackService::class);
        $callbackService
            ->expects(self::never())
            ->method('allowLoginProcess');

        $controller = $this->createController(rateLimitService: $rateLimitService, callbackService: $callbackService);
        $response = $controller->systemHubLoginCallback($request);

        self::assertSame(429, $response->getStatusCode());
        self::assertSame(['error' => 'Too many requests'], json_decode((string) $response->getContent(), true));
    }

    public function testUserLoginNewQrDoesNotBodyEnforceCsrfAnymore(): void
    {
        $request = Request::create('/api/user-login/new-qr', 'POST');
        $request->headers->set('X-CSRF-TOKEN', 'invalid-token');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects(self::once())
            ->method('getToken')
            ->with('userLoginCsrf')
            ->willReturn(new \Symfony\Component\Security\Csrf\CsrfToken('userLoginCsrf', 'fresh-token'));

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::once())
            ->method('getQrCode')
            ->willReturn(QrCodeResponseDTO::fromArray([
                'domainProcessId' => 'process-123',
                'qrCode' => 'qr-content',
            ]));

        $controller = $this->createController(qrCodeService: $qrCodeService);
        $response = $controller->userLoginNewQr($request, $csrfTokenManager);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('fresh-token', json_decode((string) $response->getContent(), true)['userLoginCsrf']);
    }

    public function testUserLoginNewQrReturnsTooManyRequestsWhenRateLimitIsExceeded(): void
    {
        $request = Request::create('/api/user-login/new-qr', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);

        $rateLimitService = $this->createRateLimitService(newQrLimit: 1);
        self::assertNull($rateLimitService->assertApiUserLoginNewQrAllowed($request));

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects(self::never())
            ->method('isTokenValid');

        $controller = $this->createController(rateLimitService: $rateLimitService);
        $response = $controller->userLoginNewQr($request, $csrfTokenManager);

        self::assertSame(429, $response->getStatusCode());
    }

    public function testUserLoginCheckReturnsUnauthorizedWhenUserIsMissing(): void
    {
        $request = Request::create('/api/user-login/check', 'POST', content: json_encode([
            'domainProcessId' => 'process-123',
        ], JSON_THROW_ON_ERROR));
        $request->headers->set('X-CSRF-TOKEN', 'valid-token');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['process' => 'process-123'])
            ->willReturn(null);

        $controller = $this->createController();
        $response = $controller->userLoginCheck($request, $userRepository);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['message' => 'Authentication failed. QR code expired.'], json_decode((string) $response->getContent(), true));
    }

    public function testUserLoginCheckReturnsTooManyRequestsWhenRateLimitIsExceeded(): void
    {
        $request = Request::create('/api/user-login/check', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);

        $rateLimitService = $this->createRateLimitService(checkLimit: 1);
        self::assertNull($rateLimitService->assertApiUserLoginCheckAllowed($request));

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::never())
            ->method('findOneBy');

        $controller = $this->createController(rateLimitService: $rateLimitService);
        $response = $controller->userLoginCheck($request, $userRepository);

        self::assertSame(429, $response->getStatusCode());
    }

    public function testUserLoginCheckDoesNotBodyEnforceCsrfAnymore(): void
    {
        $request = Request::create('/api/user-login/check', 'POST', content: json_encode([
            'domainProcessId' => 'process-123',
        ], JSON_THROW_ON_ERROR));
        $request->headers->set('X-CSRF-TOKEN', 'invalid-token');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['process' => 'process-123'])
            ->willReturn(null);

        $controller = $this->createController();
        $response = $controller->userLoginCheck($request, $userRepository);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testUserLoginCheckReturnsAuthenticationSuccessWithoutTokenInBody(): void
    {
        $request = Request::create('/api/user-login/check', 'POST', content: json_encode([
            'domainProcessId' => 'process-123',
        ], JSON_THROW_ON_ERROR));
        $request->headers->set('X-CSRF-TOKEN', 'valid-token');

        $user = (new User())
            ->setEmail('user@example.test')
            ->setPublicId('public-id')
            ->setAllowed(true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->with(['process' => 'process-123'])->willReturn($user);

        $jwtService = $this->createMock(JwtService::class);
        $jwtService
            ->expects(self::once())
            ->method('createToken')
            ->with($user)
            ->willReturn('jwt-token');
        $jwtService
            ->expects(self::once())
            ->method('createCookieFromToken')
            ->with('jwt-token')
            ->willReturn(Cookie::create('jwt_token', 'jwt-token'));

        $controller = $this->createController(jwtService: $jwtService);
    $response = $controller->userLoginCheck($request, $userRepository);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['message' => 'Authentication success'], json_decode((string) $response->getContent(), true));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame('jwt_token', $cookies[0]->getName());
        self::assertSame('jwt-token', $cookies[0]->getValue());
    }

    private function createController(
        ?LoggerInterface $logger = null,
        ?UserQrCodeService $qrCodeService = null,
        ?UserProcessCallbackService $callbackService = null,
        ?JwtService $jwtService = null,
        ?LoginApiRequestMapper $requestMapper = null,
        ?ApiRateLimitService $rateLimitService = null
    ): LoginController {
        return new LoginController(
            $logger ?? $this->createMock(LoggerInterface::class),
            $qrCodeService ?? $this->createMock(UserQrCodeService::class),
            $callbackService ?? $this->createMock(UserProcessCallbackService::class),
            $jwtService ?? $this->createMock(JwtService::class),
            $requestMapper ?? new LoginApiRequestMapper($logger ?? $this->createMock(LoggerInterface::class)),
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
