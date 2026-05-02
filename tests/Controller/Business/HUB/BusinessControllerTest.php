<?php

declare(strict_types=1);

namespace App\Tests\Controller\Business\HUB;

use App\Controller\Business\HUB\BusinessController;
use App\DTO\AuthenticatedUserDTO;
use App\DTO\BusinessContextDTO;
use App\DTO\BusinessViewDataDTO;
use App\DTO\JwtContextDTO;
use App\DTO\MenuAvailabilityDTO;
use App\Service\Business\HUB\BusinessService;
use App\Service\Corporate\SubscriptionService;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BusinessControllerTest extends TestCase
{
    public function testBusinessRendersEmptyViewWhenContextMissing(): void
    {
        $request = Request::create('/business');

        $businessService = $this->createMock(BusinessService::class);
        $businessService->method('resolveBusinessContext')->willReturn(null);
        $businessService->method('buildForms')->willReturnCallback(static fn (): never => throw new \RuntimeException('Should not be called'));
        $businessService
            ->method('buildEmptyBusinessViewData')
            ->willReturn(new BusinessViewDataDTO(false, new AuthenticatedUserDTO(), '', '', '', '', '', null, true));

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService->method('getAvailability')->willReturn(new MenuAvailabilityDTO(true, true, true));

        $controller = new TestBusinessController($businessService);
        $response = $controller->business($request, $this->createMock(SubscriptionService::class), $availabilityService);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/corporate/business-services.html.twig', $controller->renderedTemplate);
    }

    public function testBusinessRedirectsToAccountWhenSubscriptionCreated(): void
    {
        $request = Request::create('/business');
        $context = new BusinessContextDTO(
            new JwtContextDTO(true, 'public-id', 'user@example.test', ['username' => 'user@example.test']),
            new AuthenticatedUserDTO('public-id', 'user@example.test')
        );
        $forms = $this->createMock(\App\DTO\BusinessFormsDTO::class);

        $businessService = $this->createMock(BusinessService::class);
        $businessService->method('resolveBusinessContext')->willReturn($context);
        $businessService->method('buildForms')->willReturn($forms);
        $businessService->method('handleSubmittedForm')->willReturn(\App\DTO\BackendPayloadDTO::fromArray(['ok' => true]));

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService->method('getAvailability')->willReturn(new MenuAvailabilityDTO(true, true, true));

        $controller = new TestBusinessController($businessService);
        $response = $controller->business($request, $this->createMock(SubscriptionService::class), $availabilityService);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/account', $response->getTargetUrl());
    }

    public function testBusinessRendersFullViewWhenContextExistsAndNothingSubmitted(): void
    {
        $request = Request::create('/business');
        $context = new BusinessContextDTO(
            new JwtContextDTO(true, 'public-id', 'user@example.test', ['username' => 'user@example.test']),
            new AuthenticatedUserDTO('public-id', 'user@example.test')
        );
        $forms = $this->createMock(\App\DTO\BusinessFormsDTO::class);

        $businessService = $this->createMock(BusinessService::class);
        $businessService->method('resolveBusinessContext')->willReturn($context);
        $businessService->method('buildForms')->with($request)->willReturn($forms);
        $businessService->method('handleSubmittedForm')->willReturn(null);
        $businessService
            ->method('buildBusinessViewData')
            ->with($context, $forms, null, true)
            ->willReturn(new BusinessViewDataDTO(true, new AuthenticatedUserDTO('public-id', 'user@example.test'), '', '', '', '', '', null, true));

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService->method('getAvailability')->with($request)->willReturn(new MenuAvailabilityDTO(true, true, true));

        $controller = new TestBusinessController($businessService);
        $response = $controller->business($request, $this->createMock(SubscriptionService::class), $availabilityService);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/corporate/business-services.html.twig', $controller->renderedTemplate);
    }
}

final class TestBusinessController extends BusinessController
{
    public string $renderedTemplate = '';

    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $this->renderedTemplate = $view;

        return $response ?? new Response('rendered');
    }

    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    public function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return '/account';
    }
}
