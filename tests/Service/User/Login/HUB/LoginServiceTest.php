<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Login\HUB;

use App\DTO\MenuAvailabilityDTO;
use App\Entity\Process;
use App\Entity\User;
use App\Repository\ProcessRepository;
use App\Repository\UserRepository;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use App\Service\JWT\JwtService;
use App\Service\User\Login\HUB\LoginService;
use App\Service\User\UserQrCodeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class LoginServiceTest extends TestCase
{
    public function testBuildLoginViewDataReturnsExpectedDto(): void
    {
        $request = Request::create('/login', 'GET');

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('getAvailability')
            ->with($request)
            ->willReturn(new MenuAvailabilityDTO(true, true, true));

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::once())
            ->method('getQrCode')
            ->with('user_login', null, null)
            ->willReturn(
                \App\DTO\QrCodeResponseDTO::fromArray([
                    'domainProcessId' => 'process-123',
                    'qrCode' => 'qr-code-content',
                ])
            );

        $service = $this->createService(
            qrCodeService: $qrCodeService,
            availabilityService: $availabilityService
        );

        $viewData = $service->buildLoginViewData($request);

        self::assertSame('process-123', $viewData->processId);
        self::assertSame('qr-code-content', $viewData->qrCode);
        self::assertSame('login', $viewData->action);
        self::assertTrue($viewData->availabilities->availabilityUsers);
    }

    public function testBuildLoginViewDataThrowsWhenQrPayloadIncomplete(): void
    {
        $request = Request::create('/login', 'GET');

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->method('getAvailability')
            ->willReturn(new MenuAvailabilityDTO());

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::once())
            ->method('getQrCode')
            ->willReturn(\App\DTO\QrCodeResponseDTO::fromArray(['unexpected' => 'value']));

        $service = $this->createService(
            qrCodeService: $qrCodeService,
            availabilityService: $availabilityService
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Login QR code response is incomplete.');

        $service->buildLoginViewData($request);
    }

    public function testBuildFrontendPollResponseReturnsBadRequestForMissingProcessId(): void
    {
        $request = Request::create('/login/check', 'GET', ['action' => 'login']);

        $service = $this->createService();

        $response = $service->buildFrontendPollResponse($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['message' => 'Invalid poll request.'], json_decode((string) $response->getContent(), true));
    }

    public function testBuildFrontendPollResponseReturnsBadRequestForInvalidAction(): void
    {
        $request = Request::create('/login/check', 'GET', [
            'processId' => 'process-123',
            'action' => 'invalid-action',
        ]);

        $service = $this->createService();

        $response = $service->buildFrontendPollResponse($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['message' => 'Invalid poll request.'], json_decode((string) $response->getContent(), true));
    }

    public function testBuildFrontendPollResponseAuthenticatesAllowedUser(): void
    {
        $request = Request::create('/login/check', 'GET', [
            'processId' => 'process-123',
            'action' => 'login',
        ]);

        $user = (new User())
            ->setEmail('user@example.test')
            ->setPublicId('public-id')
            ->setProcess('process-123')
            ->setAllowed(true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('count')
            ->with([])
            ->willReturn(1);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['process' => 'process-123'])
            ->willReturn($user);

        $jwtService = $this->createMock(JwtService::class);
        $jwtService
            ->expects(self::once())
            ->method('createAuthenticationCookie')
            ->with($user)
            ->willReturn(Cookie::create('jwt_token', 'token-value'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($user);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(
            userRepository: $userRepository,
            jwtService: $jwtService,
            entityManager: $entityManager
        );

        $response = $service->buildFrontendPollResponse($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['message' => 'Authentication success.'], json_decode((string) $response->getContent(), true));
        self::assertCount(1, $response->headers->getCookies());
        self::assertFalse((bool) $user->isAllowed());
        self::assertNull($user->getProcess());
    }

    public function testBuildFrontendPollResponseReturnsRejectedRegistrationMessage(): void
    {
        $request = Request::create('/login/check', 'GET', [
            'processId' => 'process-123',
            'action' => 'registration',
        ]);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('count')
            ->with([])
            ->willReturn(1);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['process' => 'process-123'])
            ->willReturn(null);

        $process = (new Process())
            ->setProcessId('process-123')
            ->setAuthId('registration_rejected_whitelist');

        $processRepository = $this->createMock(ProcessRepository::class);
        $processRepository
            ->expects(self::exactly(2))
            ->method('findRejectedRegistrationProcess')
            ->with('process-123')
            ->willReturn($process);
        $processRepository
            ->expects(self::never())
            ->method('findRejectedLoginProcess');

        $service = $this->createService(
            processRepository: $processRepository,
            userRepository: $userRepository
        );

        $response = $service->buildFrontendPollResponse($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Registration rejected: this email address is not allowed.'],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function testPrepareLogoutResponseDelegatesCookieCleanup(): void
    {
        $request = new class (cookies: ['jwt_token' => 'token-value']) extends Request {
            public function __construct(array $cookies = [])
            {
                parent::__construct([], [], [], $cookies);
            }

            public function getHost(): string
            {
                return '';
            }
        };
        $response = new Response();

        $jwtService = $this->createMock(JwtService::class);
        $jwtService
            ->expects(self::once())
            ->method('getCookieName')
            ->willReturn('jwt_token');
        $jwtService
            ->expects(self::once())
            ->method('clearAuthenticationCookie')
            ->with($response, $request);

        $service = $this->createService(jwtService: $jwtService);

        $service->prepareLogoutResponse($response, $request);

        self::assertTrue(true);
    }

    public function testBuildLoginViewDataRejectsInvalidUserPublicIdLength(): void
    {
        $request = Request::create('/login', 'GET', [
            'userPublicId' => 'too-short',
        ]);

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->method('getAvailability')
            ->willReturn(new MenuAvailabilityDTO());

        $service = $this->createService(availabilityService: $availabilityService);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid length.');

        $service->buildLoginViewData($request);
    }

    private function createService(
        ?LoggerInterface $logger = null,
        ?UserQrCodeService $qrCodeService = null,
        ?ProcessRepository $processRepository = null,
        ?UserRepository $userRepository = null,
        ?RegistrationMenuAvailabilityService $availabilityService = null,
        ?JwtService $jwtService = null,
        ?EntityManagerInterface $entityManager = null
    ): LoginService {
        return new LoginService(
            $logger ?? $this->createMock(LoggerInterface::class),
            $qrCodeService ?? $this->createMock(UserQrCodeService::class),
            $processRepository ?? $this->createMock(ProcessRepository::class),
            $userRepository ?? $this->createMock(UserRepository::class),
            $availabilityService ?? $this->createMock(RegistrationMenuAvailabilityService::class),
            $jwtService ?? $this->createMock(JwtService::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
        );
    }
}
