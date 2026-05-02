<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Attribute\ClientAuthRequired;
use App\EventListener\ClientAuthListener;
use App\Service\Security\ApiClientAuthGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ClientAuthListenerTest extends TestCase
{
    public function testNonArrayControllerIsIgnored(): void
    {
        $request = Request::create('/api/protected', 'POST');

        $listener = new ClientAuthListener(new ApiClientAuthGuard(), $this->createMock(LoggerInterface::class));
        $controller = static fn (): string => 'ok';
        $event = $this->createControllerEvent($request, $controller);

        $listener($event);

        self::assertSame($controller, $event->getController());
    }

    public function testControllerWithoutAttributeIsIgnored(): void
    {
        $request = Request::create('/api/public', 'GET');

        $listener = new ClientAuthListener(new ApiClientAuthGuard(), $this->createMock(LoggerInterface::class));
        $controller = [new ClientAuthPublicController(), 'publicAction'];
        $event = $this->createControllerEvent($request, $controller);

        $listener($event);

        self::assertSame($controller, $event->getController());
        self::assertFalse($request->attributes->has(ApiClientAuthGuard::REQUEST_ATTRIBUTE));
    }

    public function testProtectedClientAuthRouteRejectsMissingHeader(): void
    {
        $request = Request::create('/api/protected', 'POST');
        $listener = new ClientAuthListener(new ApiClientAuthGuard(), $this->createMock(LoggerInterface::class));

        $event = $this->createControllerEvent($request, [new ClientAuthProtectedController(), 'protectedAction']);
        $listener($event);

        $response = $event->getController()();

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Missing X-Client-Auth header!'], json_decode((string) $response->getContent(), true));
    }

    public function testProtectedClientAuthRouteStoresValidatedHeaderOnRequest(): void
    {
        $request = Request::create('/api/protected', 'POST');
        $request->headers->set('x-client-auth', 'header-value');

        $listener = new ClientAuthListener(new ApiClientAuthGuard(), $this->createMock(LoggerInterface::class));
        $event = $this->createControllerEvent($request, [new ClientAuthProtectedController(), 'protectedAction']);

        $listener($event);

        $controller = $event->getController();

        self::assertSame('header-value', $request->attributes->get(ApiClientAuthGuard::REQUEST_ATTRIBUTE));
        self::assertIsArray($controller);
        self::assertInstanceOf(ClientAuthProtectedController::class, $controller[0]);
        self::assertSame('protectedAction', $controller[1]);
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
}

final class ClientAuthProtectedController
{
    #[ClientAuthRequired]
    public function protectedAction(): void
    {
    }
}

final class ClientAuthPublicController
{
    public function publicAction(): void
    {
    }
}
