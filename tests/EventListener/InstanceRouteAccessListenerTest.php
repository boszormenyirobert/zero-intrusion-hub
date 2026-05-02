<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Attribute\InitializationOnlyRoute;
use App\Attribute\InitializationOrJwtRoute;
use App\EventListener\InstanceRouteAccessListener;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class InstanceRouteAccessListenerTest extends TestCase
{
    public function testInitializationOnlyRouteRejectsWhenInitializationIsCompleted(): void
    {
        $request = Request::create('/settings');
        $request->attributes->set('_route', 'settings');

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('canAccessManagementRoute')
            ->with($request)
            ->willReturn(false);

        $listener = new InstanceRouteAccessListener(
            $availabilityService,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $event = $this->createControllerEvent($request, [new InstanceInitializationOnlyController(), 'settings']);
        $listener($event);

        $response = $event->getController()();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/instance_login', $response->getTargetUrl());
    }

    public function testInitializationOnlyRouteAllowsWhenInitializationIsActive(): void
    {
        $request = Request::create('/settings');

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('canAccessManagementRoute')
            ->with($request)
            ->willReturn(true);

        $listener = new InstanceRouteAccessListener(
            $availabilityService,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $controller = [new InstanceInitializationOnlyController(), 'settings'];
        $event = $this->createControllerEvent($request, $controller);
        $listener($event);

        self::assertSame($controller, $event->getController());
    }

    public function testInitializationOrJwtRouteRejectsWhenAccessIsDenied(): void
    {
        $request = Request::create('/access');
        $request->attributes->set('_route', 'access');

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('canAccessUsersRoute')
            ->with($request)
            ->willReturn(false);

        $listener = new InstanceRouteAccessListener(
            $availabilityService,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $event = $this->createControllerEvent($request, [new InstanceInitializationOrJwtController(), 'access']);
        $listener($event);

        $response = $event->getController()();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/instance_login', $response->getTargetUrl());
    }

    public function testInitializationOrJwtRouteAllowsWhenAccessIsGranted(): void
    {
        $request = Request::create('/access');

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('canAccessUsersRoute')
            ->with($request)
            ->willReturn(true);

        $listener = new InstanceRouteAccessListener(
            $availabilityService,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $controller = [new InstanceInitializationOrJwtController(), 'access'];
        $event = $this->createControllerEvent($request, $controller);
        $listener($event);

        self::assertSame($controller, $event->getController());
    }

    private function createControllerEvent(Request $request, callable|array $controller): ControllerEvent
    {
        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            $controller,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    private function createUrlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->with('instance_login')->willReturn('/instance_login');

        return $urlGenerator;
    }
}

final class InstanceInitializationOnlyController
{
    #[InitializationOnlyRoute]
    public function settings(): void
    {
    }
}

final class InstanceInitializationOrJwtController
{
    #[InitializationOrJwtRoute]
    public function access(): void
    {
    }
}