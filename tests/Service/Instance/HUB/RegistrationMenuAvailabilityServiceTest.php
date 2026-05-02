<?php

declare(strict_types=1);

namespace App\Tests\Service\Instance\HUB;

use App\DTO\JwtContextDTO;
use App\Service\Instance\HUB\InstanceRegistrationService;
use App\Service\Instance\HUB\JwtContextService;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class RegistrationMenuAvailabilityServiceTest extends TestCase
{
    public function testExternalInstanceRegistrationAvailabilityRequiresValidJwt(): void
    {
        $request = Request::create('/instance-registration-external');

        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->method('build')
            ->with($request)
            ->willReturn(new JwtContextDTO(true, 'public-id', 'user@example.test', [
                'publicId' => 'public-id',
                'username' => 'user@example.test',
            ]));

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(false),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertTrue($service->isExternalInstanceRegistrationAvailable($request));
    }

    public function testExternalInstanceRegistrationAvailabilityRejectsInvalidJwt(): void
    {
        $request = Request::create('/instance-registration-external');

        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->method('build')
            ->with($request)
            ->willReturn(JwtContextDTO::invalid());

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(false),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertFalse($service->isExternalInstanceRegistrationAvailable($request));
    }

    public function testInstanceRegistrationFollowUpAvailabilityRequiresValidJwt(): void
    {
        $request = Request::create('/instance-registration-follow-up');

        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->method('build')
            ->with($request)
            ->willReturn(new JwtContextDTO(true, 'public-id', 'user@example.test', [
                'publicId' => 'public-id',
                'username' => 'user@example.test',
            ]));

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(false),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertTrue($service->isInstanceRegistrationFollowUpAvailable($request));
    }

    public function testInstanceRegistrationFollowUpAvailabilityRejectsInvalidJwt(): void
    {
        $request = Request::create('/instance-registration-follow-up');

        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->method('build')
            ->with($request)
            ->willReturn(JwtContextDTO::invalid());

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(false),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertFalse($service->isInstanceRegistrationFollowUpAvailable($request));
    }

    public function testGetAvailabilityKeepsUsersAvailableDuringInitializationWithoutJwt(): void
    {
        $request = Request::create('/');
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->expects(self::never())
            ->method('build');

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(true),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        $availability = $service->getAvailability($request);

        self::assertTrue($availability->availabilitySettings);
        self::assertTrue($availability->availabilityInstance);
        self::assertTrue($availability->availabilityUsers);
    }

    public function testGetAvailabilityKeepsSettingsAndUsersOpenDuringInitializationWithoutJwt(): void
    {
        $request = Request::create('/');
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->expects(self::never())
            ->method('build');

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(true),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        $availability = $service->getAvailability($request);

        self::assertTrue($availability->availabilitySettings);
        self::assertTrue($availability->availabilityInstance);
        self::assertTrue($availability->availabilityUsers);
    }

    public function testGetAvailabilityBlocksSettingsAndUsersAfterInitializationWithoutJwt(): void
    {
        $request = Request::create('/');
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->method('build')
            ->with($request)
            ->willReturn(JwtContextDTO::invalid());

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(false),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        $availability = $service->getAvailability($request);

        self::assertFalse($availability->availabilitySettings);
        self::assertFalse($availability->availabilityInstance);
        self::assertFalse($availability->availabilityUsers);
    }

    public function testGetAvailabilityBlocksSettingsButAllowsUsersWithValidJwtAfterInitialization(): void
    {
        $request = Request::create('/');
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->method('build')
            ->with($request)
            ->willReturn(new JwtContextDTO(true, 'public-id', 'user@example.test', [
                'publicId' => 'public-id',
                'username' => 'user@example.test',
            ]));

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(false),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        $availability = $service->getAvailability($request);

        self::assertFalse($availability->availabilitySettings);
        self::assertFalse($availability->availabilityInstance);
        self::assertTrue($availability->availabilityUsers);
    }

    public function testCanAccessUsersRouteAllowsAnonymousAccessDuringInitialization(): void
    {
        $request = Request::create('/access');
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->expects(self::never())
            ->method('build')
            ->with($request);

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(true),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertTrue($service->canAccessUsersRoute($request));
    }

    public function testCanAccessManagementRouteAllowsAnonymousAccessDuringInitialization(): void
    {
        $request = Request::create('/settings');
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->expects(self::never())
            ->method('build');

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(true),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertTrue($service->canAccessManagementRoute($request));
    }

    public function testCanAccessManagementRouteAlwaysBlocksAfterInitialization(): void
    {
        $request = Request::create('/settings');
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->expects(self::never())
            ->method('build')
            ->with($request);

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(false),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertFalse($service->canAccessManagementRoute($request));
    }

    public function testCanAccessUsersRouteBlocksAnonymousAccessAfterInitialization(): void
    {
        $request = Request::create('/access');
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->expects(self::once())
            ->method('build')
            ->with($request)
            ->willReturn(JwtContextDTO::invalid());

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(false),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertFalse($service->canAccessUsersRoute($request));
    }

    public function testQueryParameterCannotBypassInitializationState(): void
    {
        $request = Request::create('/settings?InstanceRegistration=1');

        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->expects(self::never())
            ->method('build');

        $service = new RegistrationMenuAvailabilityService(
            $this->createInstanceRegistrationService(false),
            $jwtContextService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertFalse($service->canAccessManagementRoute($request));
    }

    private function createInstanceRegistrationService(bool $initializationState): InstanceRegistrationService
    {
        $service = $this->createMock(InstanceRegistrationService::class);
        $service
            ->method('getInitializationState')
            ->willReturn($initializationState);

        return $service;
    }
}