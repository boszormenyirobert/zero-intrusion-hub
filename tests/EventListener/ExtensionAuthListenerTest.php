<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Attribute\ExtensionAuthRequired;
use App\EventListener\ExtensionAuthListener;
use App\Service\Security\ExtensionAuthGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ExtensionAuthListenerTest extends TestCase
{
    public function testProtectedExtensionAuthRouteRejectsMissingHeader(): void
    {
        $request = Request::create('/api/credential-hub/protected', 'POST');
        $listener = new ExtensionAuthListener(new ExtensionAuthGuard(), $this->createMock(LoggerInterface::class));

        $event = $this->createControllerEvent($request, [new ExtensionAuthProtectedController(), 'protectedAction']);
        $listener($event);

        $response = $event->getController()();

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Missing X-Extension-Auth header!'], json_decode((string) $response->getContent(), true));
    }

    public function testProtectedExtensionAuthRouteStoresValidatedHeaderOnRequest(): void
    {
        $request = Request::create('/api/credential-hub/protected', 'POST');
        $request->headers->set('X-Extension-Auth', 'extension-header');

        $listener = new ExtensionAuthListener(new ExtensionAuthGuard(), $this->createMock(LoggerInterface::class));
        $event = $this->createControllerEvent($request, [new ExtensionAuthProtectedController(), 'protectedAction']);

        $listener($event);

        $controller = $event->getController();

        self::assertSame('extension-header', $request->attributes->get(ExtensionAuthGuard::REQUEST_ATTRIBUTE));
        self::assertIsArray($controller);
        self::assertInstanceOf(ExtensionAuthProtectedController::class, $controller[0]);
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

final class ExtensionAuthProtectedController
{
    #[ExtensionAuthRequired]
    public function protectedAction(): void
    {
    }
}