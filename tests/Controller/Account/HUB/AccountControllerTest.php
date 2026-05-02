<?php

declare(strict_types=1);

namespace App\Tests\Controller\Account\HUB;

use App\Attribute\JwtRequired;
use App\Controller\Account\HUB\AccountController;
use App\DTO\AccountContextDTO;
use App\DTO\AccountViewDataDTO;
use App\DTO\AuthenticatedUserDTO;
use App\DTO\BusinessSubscriptionDataDTO;
use App\DTO\JwtContextDTO;
use App\DTO\MenuAvailabilityDTO;
use App\Service\Account\HUB\AccountService;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AccountControllerTest extends TestCase
{
    public function testAccountRouteRequiresJwtAttribute(): void
    {
        $reflectionMethod = new \ReflectionMethod(AccountController::class, 'account');

        self::assertNotEmpty($reflectionMethod->getAttributes(JwtRequired::class));
    }

    public function testAccountRendersTypedViewData(): void
    {
        $request = Request::create('/account');
        $accountContext = new AccountContextDTO(
            new JwtContextDTO(true, 'public-id', 'user@example.test', ['username' => 'user@example.test']),
            new AuthenticatedUserDTO('public-id', 'user@example.test')
        );
        $businessSubscription = BusinessSubscriptionDataDTO::fromArray([
            'accounts' => [['id' => 1]],
            'businessSubscription' => ['id' => 9, 'pro' => true],
        ]);

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->method('resolveAccountContext')
            ->with($request)
            ->willReturn($accountContext);
        $accountService
            ->method('loadBusinessSubscription')
            ->willReturn($businessSubscription);
        $accountService
            ->method('buildAccountViewData')
            ->willReturn(new AccountViewDataDTO(
                true,
                new AuthenticatedUserDTO('public-id', 'user@example.test'),
                [['id' => 1]],
                null,
                true,
                ['pro' => 'Business Pro']
            ));

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->method('getAvailability')
            ->willReturn(new MenuAvailabilityDTO(true, true, true));

        $controller = new TestAccountController($accountService);
        $response = $controller->account($request, $this->createMock(BackendForwardingService::class), $availabilityService);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/containers/container-account.html.twig', $controller->renderedTemplate);
        self::assertSame('user@example.test', $controller->renderedParameters['user']['userEmail']);
    }

    public function testAccountRedirectsToLoginWhenContextMissing(): void
    {
        $request = Request::create('/account');

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->method('resolveAccountContext')
            ->willReturn(null);

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->method('getAvailability')
            ->willReturn(new MenuAvailabilityDTO());

        $controller = new TestAccountController($accountService);
        $response = $controller->account($request, $this->createMock(BackendForwardingService::class), $availabilityService);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/instance-login', $response->getTargetUrl());
    }
}

final class TestAccountController extends AccountController
{
    public string $renderedTemplate = '';
    public array $renderedParameters = [];

    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $this->renderedTemplate = $view;
        $this->renderedParameters = $parameters;

        return $response ?? new Response('rendered');
    }

    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    public function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return '/instance-login';
    }
}