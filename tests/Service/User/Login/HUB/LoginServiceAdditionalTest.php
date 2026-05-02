<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Login\HUB;

use App\DTO\MenuAvailabilityDTO;
use App\DTO\QrCodeResponseDTO;
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
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

final class LoginServiceAdditionalTest extends TestCase
{
    public function testBuildLoginViewDataPassesValidUserPublicIdToQrService(): void
    {
        $validUserPublicId = rtrim(base64_encode(str_repeat('u', 32)), '=');
        $request = Request::create('/login', 'GET', ['userPublicId' => $validUserPublicId]);

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService->method('getAvailability')->willReturn(new MenuAvailabilityDTO(true, true, true));

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::once())
            ->method('getQrCode')
            ->with('user_login', null, $validUserPublicId)
            ->willReturn(QrCodeResponseDTO::fromArray([
                'domainProcessId' => 'process-123',
                'qrCode' => 'qr-code-content',
            ]));

        $service = $this->createService(qrCodeService: $qrCodeService, availabilityService: $availabilityService);
        $viewData = $service->buildLoginViewData($request);

        self::assertSame('process-123', $viewData->processId);
    }

    public function testBuildLoginViewDataRejectsUserPublicIdWithInvalidCharacters(): void
    {
        $request = Request::create('/login', 'GET', ['userPublicId' => str_repeat('a', 42) . '*']);

        $service = $this->createService(availabilityService: $this->availabilityService());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid characters.');
        $service->buildLoginViewData($request);
    }

    public function testBuildLoginViewDataRejectsUserPublicIdWithInvalidDecodedPayload(): void
    {
        $request = Request::create('/login', 'GET', ['userPublicId' => str_repeat('Q', 42)]);

        $service = $this->createService(availabilityService: $this->availabilityService());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token.');
        $service->buildLoginViewData($request);
    }

    public function testBuildLoginViewDataRejectsNonScalarUserPublicIdQueryParameter(): void
    {
        $request = Request::create('/login', 'GET');
        $request->query->set('userPublicId', ['unexpected']);

        $service = $this->createService(availabilityService: $this->availabilityService());

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('contains a non-scalar value');
        $service->buildLoginViewData($request);
    }

    public function testBuildFrontendPollResponseReturnsRegistrationSuccessWhenUserExists(): void
    {
        $request = Request::create('/login/check', 'GET', ['processId' => 'process-123', 'action' => 'registration']);
        $user = (new User())->setEmail('user@example.test')->setPublicId('public-id')->setProcess('process-123');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('count')->willReturn(1);
        $userRepository->expects(self::once())->method('findOneBy')->with(['process' => 'process-123'])->willReturn($user);

        $response = $this->createService(userRepository: $userRepository)->buildFrontendPollResponse($request);

        self::assertSame(['message' => 'Registration success.'], json_decode((string) $response->getContent(), true));
    }

    public function testBuildFrontendPollResponseReturnsRejectedDuplicateRegistrationMessage(): void
    {
        $request = Request::create('/login/check', 'GET', ['processId' => 'process-123', 'action' => 'registration']);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('count')->willReturn(1);
        $userRepository->method('findOneBy')->willReturn(null);

        $processRepository = $this->createMock(ProcessRepository::class);
        $processRepository->expects(self::exactly(2))->method('findRejectedRegistrationProcess')->with('process-123')->willReturn((new Process())->setProcessId('process-123')->setAuthId('registration_rejected_duplicate_user'));

        $response = $this->createService(processRepository: $processRepository, userRepository: $userRepository)->buildFrontendPollResponse($request);

        self::assertSame(['message' => 'Registration rejected: this email and public ID are already registered.'], json_decode((string) $response->getContent(), true));
    }

    public function testBuildFrontendPollResponseReturnsRejectedLoginMessage(): void
    {
        $request = Request::create('/login/check', 'GET', ['processId' => 'process-123', 'action' => 'login']);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('count')->willReturn(1);
        $userRepository->method('findOneBy')->willReturn(null);

        $processRepository = $this->createMock(ProcessRepository::class);
        $processRepository->expects(self::exactly(2))->method('findRejectedLoginProcess')->with('process-123')->willReturn((new Process())->setProcessId('process-123')->setAuthId('login_rejected_whitelist'));

        $response = $this->createService(processRepository: $processRepository, userRepository: $userRepository)->buildFrontendPollResponse($request);

        self::assertSame(['message' => 'Authentication rejected: access has been revoked for this email address.'], json_decode((string) $response->getContent(), true));
    }

    public function testBuildFrontendPollResponseReturnsBadRequestWhenActionMissing(): void
    {
        $request = Request::create('/login/check', 'GET', ['processId' => 'process-123']);

        $response = $this->createService()->buildFrontendPollResponse($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['message' => 'Invalid poll request.'], json_decode((string) $response->getContent(), true));
    }

    public function testPrepareLogoutResponseWorksWithoutExistingCookie(): void
    {
        $response = new \Symfony\Component\HttpFoundation\Response();
        $request = new class () extends Request {
            public function getHost(): string
            {
                return '';
            }
        };

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->expects(self::once())->method('getCookieName')->willReturn('jwt_token');
        $jwtService->expects(self::once())->method('clearAuthenticationCookie')->with($response, $request);

        $this->createService(jwtService: $jwtService)->prepareLogoutResponse($response, $request);

        self::assertTrue(true);
    }

    private function availabilityService(): RegistrationMenuAvailabilityService
    {
        $service = $this->createMock(RegistrationMenuAvailabilityService::class);
        $service->method('getAvailability')->willReturn(new MenuAvailabilityDTO());

        return $service;
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
