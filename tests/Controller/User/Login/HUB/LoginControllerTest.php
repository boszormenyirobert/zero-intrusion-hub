<?php

declare(strict_types=1);

namespace App\Tests\Controller\User\Login\HUB;

use App\Attribute\CsrfProtectedRoute;
use App\Attribute\PublicRoute;
use App\Controller\User\Login\HUB\LoginController;
use App\DTO\LoginViewDataDTO;
use App\DTO\MenuAvailabilityDTO;
use App\DTO\QrCodeResponseDTO;
use App\Service\User\Login\HUB\LoginService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class LoginControllerTest extends TestCase
{
    public function testLoginRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(LoginController::class, 'login');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testFrontendPollRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(LoginController::class, 'pollStateByFrontend');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testLogoutRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(LoginController::class, 'logout');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testLogoutRouteIsExplicitlyMarkedAsCsrfProtected(): void
    {
        $reflectionMethod = new \ReflectionMethod(LoginController::class, 'logout');

        $attributes = $reflectionMethod->getAttributes(CsrfProtectedRoute::class);

        self::assertNotEmpty($attributes);
        self::assertSame('userLogout', $attributes[0]->newInstance()->tokenId);
    }

    public function testLoginRendersTypedViewData(): void
    {
        $request = Request::create('/login');

        $loginService = $this->createMock(LoginService::class);
        $loginService
            ->method('buildLoginViewData')
            ->with($request)
            ->willReturn(new LoginViewDataDTO(
                'process-123',
                QrCodeResponseDTO::fromArray(['domainProcessId' => 'process-123', 'qrCode' => 'qr-code']),
                'qr-code',
                [],
                'login',
                new MenuAvailabilityDTO(true, true, true)
            ));

        $controller = new TestHubLoginController($loginService);
        $response = $controller->login($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/users/user-login.html.twig', $controller->renderedTemplate);
        self::assertSame('process-123', $controller->renderedParameters['processId']);
    }

    public function testLogoutPreparesResponseWhenCsrfWasAlreadyValidatedUpstream(): void
    {
        $request = Request::create('/user-logout', 'POST', ['_token' => 'any-token']);

        $loginService = $this->createMock(LoginService::class);
        $loginService
            ->expects(self::once())
            ->method('prepareLogoutResponse')
            ->with(self::isInstanceOf(Response::class), $request);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects(self::once())
            ->method('removeToken')
            ->with('userLogout');

        $controller = new TestHubLoginController($loginService);
        $response = $controller->logout($csrfTokenManager, $request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/home', $response->getTargetUrl());
    }
}

final class TestHubLoginController extends LoginController
{
    public string $renderedTemplate = '';
    public array $renderedParameters = [];

    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $this->renderedTemplate = $view;
        $this->renderedParameters = $parameters;

        return $response ?? new Response('rendered');
    }

    protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        return new RedirectResponse('/' . $route, $status);
    }
}