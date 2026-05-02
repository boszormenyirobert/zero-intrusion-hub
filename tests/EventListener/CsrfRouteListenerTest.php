<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Attribute\CsrfProtectedRoute;
use App\EventListener\CsrfRouteListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class CsrfRouteListenerTest extends TestCase
{
    public function testNonArrayControllerIsIgnored(): void
    {
        $request = Request::create('/plain', 'GET');
        $listener = new CsrfRouteListener(
            $this->createMock(CsrfTokenManagerInterface::class),
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $controller = static fn (): string => 'ok';
        $event = $this->createControllerEvent($request, $controller);

        $listener($event);

        self::assertSame($controller, $event->getController());
    }

    public function testControllerWithoutCsrfAttributeIsIgnored(): void
    {
        $request = Request::create('/plain', 'GET');
        $listener = new CsrfRouteListener(
            $this->createMock(CsrfTokenManagerInterface::class),
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $controller = [new NonProtectedController(), 'index'];
        $event = $this->createControllerEvent($request, $controller);

        $listener($event);

        self::assertSame($controller, $event->getController());
    }

    public function testCsrfProtectedRouteRejectsInvalidTokenWithAccessDeniedWhenNoFailureRouteIsConfigured(): void
    {
        $request = Request::create('/user-logout', 'POST', ['_token' => 'invalid-token']);
        $request->attributes->set('_route', 'instance_logout');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects(self::once())
            ->method('isTokenValid')
            ->with(self::callback(static fn (CsrfToken $token): bool => $token->getId() === 'userLogout' && $token->getValue() === 'invalid-token'))
            ->willReturn(false);

        $listener = new CsrfRouteListener(
            $csrfTokenManager,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $event = $this->createControllerEvent($request, [new CsrfProtectedLogoutController(), 'logout']);
        $listener($event);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

        $event->getController()();
    }

    public function testCsrfProtectedRouteRedirectsWhenFailureRouteIsConfigured(): void
    {
        $request = Request::create('/access/5/status', 'POST', ['_token' => 'invalid-token']);
        $request->attributes->set('_route', 'access_user_status');
        $request->attributes->set('id', 5);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects(self::once())
            ->method('isTokenValid')
            ->with(self::callback(static fn (CsrfToken $token): bool => $token->getId() === 'whitelisted_user_status_5' && $token->getValue() === 'invalid-token'))
            ->willReturn(false);

        $listener = new CsrfRouteListener(
            $csrfTokenManager,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $event = $this->createControllerEvent($request, [new CsrfProtectedRedirectController(), 'updateStatus']);
        $listener($event);

        $response = $event->getController()();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/access', $response->getTargetUrl());
    }

    public function testCsrfProtectedRouteAllowsControllerWhenTokenIsValid(): void
    {
        $request = Request::create('/access/5/status', 'POST', ['_token' => 'valid-token']);
        $request->attributes->set('id', 5);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects(self::once())
            ->method('isTokenValid')
            ->willReturn(true);

        $listener = new CsrfRouteListener(
            $csrfTokenManager,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $controller = [new CsrfProtectedRedirectController(), 'updateStatus'];
        $event = $this->createControllerEvent($request, $controller);
        $listener($event);

        self::assertSame($controller, $event->getController());
    }

    public function testCsrfProtectedRouteCanReadTokenFromHeaderAndUseCustomFailureMessage(): void
    {
        $request = Request::create('/api/user-login/check', 'POST');
        $request->attributes->set('_route', 'user_login_check');
        $request->headers->set('X-CSRF-TOKEN', 'invalid-token');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects(self::once())
            ->method('isTokenValid')
            ->with(self::callback(static fn (CsrfToken $token): bool => $token->getId() === 'userLoginCsrf' && $token->getValue() === 'invalid-token'))
            ->willReturn(false);

        $listener = new CsrfRouteListener(
            $csrfTokenManager,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $event = $this->createControllerEvent($request, [new HeaderCsrfProtectedController(), 'check']);
        $listener($event);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid CSRF token');

        $event->getController()();
    }

    public function testCsrfProtectedRouteAcceptsValidHeaderToken(): void
    {
        $request = Request::create('/api/user-login/check', 'POST');
        $request->headers->set('X-CSRF-TOKEN', 'valid-token');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects(self::once())
            ->method('isTokenValid')
            ->with(self::callback(static fn (CsrfToken $token): bool => $token->getId() === 'userLoginCsrf' && $token->getValue() === 'valid-token'))
            ->willReturn(true);

        $listener = new CsrfRouteListener(
            $csrfTokenManager,
            $this->createUrlGenerator(),
            $this->createMock(LoggerInterface::class)
        );

        $controller = [new HeaderCsrfProtectedController(), 'check'];
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
        $urlGenerator->method('generate')->with('access')->willReturn('/access');

        return $urlGenerator;
    }
}

final class CsrfProtectedLogoutController
{
    #[CsrfProtectedRoute(tokenId: 'userLogout')]
    public function logout(): void
    {
    }
}

final class CsrfProtectedRedirectController
{
    #[CsrfProtectedRoute(tokenId: 'whitelisted_user_status_{id}', failureRoute: 'access')]
    public function updateStatus(): void
    {
    }
}

final class HeaderCsrfProtectedController
{
    #[CsrfProtectedRoute(tokenId: 'userLoginCsrf', tokenField: 'X-CSRF-TOKEN', tokenSource: 'header', failureMessage: 'Invalid CSRF token')]
    public function check(): void
    {
    }
}

final class NonProtectedController
{
    public function index(): void
    {
    }
}
