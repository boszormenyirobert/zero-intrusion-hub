<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Attribute\JwtRequired;
use App\EventListener\JwtAuthListener;
use App\Service\JWT\JwtService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class JwtAuthListenerTest extends TestCase
{
    public function testListenerIgnoresNonArrayController(): void
    {
        $listener = new JwtAuthListener(
            $this->createMock(JwtService::class),
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $controller = static fn (): null => null;
        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            $controller,
            Request::create('/account'),
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener($event);

        self::assertSame($controller, $event->getController());
    }

    public function testListenerIgnoresControllerWithoutJwtRequirement(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $jwtService->expects(self::never())->method('extractPayloadFromRequest');

        $listener = new JwtAuthListener(
            $jwtService,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $controller = [new PublicController(), 'action'];
        $event = $this->createControllerEvent(Request::create('/public'), $controller);
        $listener($event);

        self::assertSame($controller, $event->getController());
    }

    public function testListenerRedirectsWhenJwtIsInvalid(): void
    {
        $request = Request::create('/account');
        $request->attributes->set('_route', 'account');

        $jwtService = $this->createMock(JwtService::class);
        $jwtService
            ->expects(self::once())
            ->method('extractPayloadFromRequest')
            ->with($request)
            ->willReturn(null);

        $listener = new JwtAuthListener(
            $jwtService,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $event = $this->createControllerEvent($request, [new ProtectedController(), 'action']);
        $listener($event);

        $response = $event->getController()();
        self::assertSame(false, $request->attributes->get('is_jwt_valid'));
        self::assertSame('/instance_login', $response->getTargetUrl());
    }

    public function testListenerAllowsProtectedControllerWhenJwtIsValid(): void
    {
        $request = Request::create('/account');
        $request->attributes->set('_route', 'account');

        $jwtService = $this->createMock(JwtService::class);
        $jwtService
            ->expects(self::once())
            ->method('extractPayloadFromRequest')
            ->with($request)
            ->willReturn(['username' => 'user@example.test']);

        $listener = new JwtAuthListener(
            $jwtService,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $controller = [new ProtectedController(), 'action'];
        $event = $this->createControllerEvent($request, $controller);
        $listener($event);

        self::assertTrue((bool) $request->attributes->get('is_jwt_valid'));
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
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->method('generate')->with('instance_login')->willReturn('/instance_login');

        return $generator;
    }
}

final class ProtectedController
{
    #[JwtRequired]
    public function action(): void
    {
    }
}

final class PublicController
{
    public function action(): void
    {
    }
}
