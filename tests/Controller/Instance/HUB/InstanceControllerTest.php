<?php

declare(strict_types=1);

namespace App\Tests\Controller\Instance\HUB;

use App\Attribute\CsrfProtectedRoute;
use App\Attribute\InitializationOnlyRoute;
use App\Attribute\InitializationOrJwtRoute;
use App\Attribute\PublicRoute;
use App\Controller\Instance\HUB\InstanceController;
use App\DTO\AuthenticatedUserDTO;
use App\DTO\InstanceHomeViewDataDTO;
use App\DTO\InstanceUsersViewDataDTO;
use App\DTO\MenuAvailabilityDTO;
use App\Service\Instance\HUB\InstanceService;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use App\Service\Instance\HUB\SettingsFormHandler;
use App\Service\Instance\HUB\WhitelistedUserFormHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class InstanceControllerTest extends TestCase
{
    public function testHomeRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(InstanceController::class, 'home');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testSettingsRouteIsExplicitlyMarkedAsInitializationOnly(): void
    {
        $reflectionMethod = new \ReflectionMethod(InstanceController::class, 'settings');

        self::assertNotEmpty($reflectionMethod->getAttributes(InitializationOnlyRoute::class));
    }

    public function testAccessRouteIsExplicitlyMarkedAsInitializationOrJwt(): void
    {
        $reflectionMethod = new \ReflectionMethod(InstanceController::class, 'access');

        self::assertNotEmpty($reflectionMethod->getAttributes(InitializationOrJwtRoute::class));
    }

    public function testUpdateWhitelistedUserStatusRouteIsExplicitlyMarkedAsInitializationOrJwtAndCsrfProtected(): void
    {
        $reflectionMethod = new \ReflectionMethod(InstanceController::class, 'updateWhitelistedUserStatus');
        $csrfAttribute = $reflectionMethod->getAttributes(CsrfProtectedRoute::class)[0]->newInstance();

        self::assertNotEmpty($reflectionMethod->getAttributes(InitializationOrJwtRoute::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(CsrfProtectedRoute::class));
        self::assertSame('whitelisted_user_status_{id}', $csrfAttribute->tokenId);
        self::assertSame('access', $csrfAttribute->failureRoute);
    }

    public function testDeleteWhitelistedUserRouteIsExplicitlyMarkedAsInitializationOrJwtAndCsrfProtected(): void
    {
        $reflectionMethod = new \ReflectionMethod(InstanceController::class, 'deleteWhitelistedUser');
        $csrfAttribute = $reflectionMethod->getAttributes(CsrfProtectedRoute::class)[0]->newInstance();

        self::assertNotEmpty($reflectionMethod->getAttributes(InitializationOrJwtRoute::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(CsrfProtectedRoute::class));
        self::assertSame('whitelisted_user_delete_{id}', $csrfAttribute->tokenId);
        self::assertSame('access', $csrfAttribute->failureRoute);
    }

    public function testHomeRendersTypedViewData(): void
    {
        $request = Request::create('/');

        $instanceService = $this->createMock(InstanceService::class);
        $instanceService
            ->expects(self::once())
            ->method('buildHomeViewData')
            ->with($request)
            ->willReturn(new InstanceHomeViewDataDTO(
                true,
                new AuthenticatedUserDTO('public-id', 'user@example.test'),
                new MenuAvailabilityDTO(true, true, true)
            ));

        $controller = new TestInstanceController($instanceService, $this->createMock(FormInterface::class));
        $response = $controller->home($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/containers/container-home.html.twig', $controller->renderedTemplate);
        self::assertSame('user@example.test', $controller->renderedParameters['user']['userEmail']);
    }

    public function testSettingsRendersTypedViewDataWhenRouteAccessAlreadyAllowed(): void
    {
        $request = Request::create('/settings');
        $formView = new FormView();

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('createView')->willReturn($formView);

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('getAvailability')
            ->with($request)
            ->willReturn(new MenuAvailabilityDTO(true, true, true));

        $settingsFormHandler = $this->createMock(SettingsFormHandler::class);
        $settingsFormHandler->method('handle')->with($form)->willReturn(false);

        $instanceService = $this->createMock(InstanceService::class);
        $instanceService
            ->expects(self::once())
            ->method('buildSettingsViewData')
            ->with($request, self::isInstanceOf(MenuAvailabilityDTO::class), $formView)
            ->willReturn(new \App\DTO\InstanceSettingsViewDataDTO(
                false,
                new AuthenticatedUserDTO(),
                new MenuAvailabilityDTO(true, true, true),
                $formView
            ));

        $controller = new TestInstanceController($instanceService, $form);
        $response = $controller->settings($request, $availabilityService, $settingsFormHandler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/containers/container-settings.html.twig', $controller->renderedTemplate);
    }

    public function testSettingsRedirectsToHomeWhenFormHandlerSucceeds(): void
    {
        $request = Request::create('/settings');
        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService->expects(self::once())->method('getAvailability')->with($request)->willReturn(new MenuAvailabilityDTO(true, true, true));

        $settingsFormHandler = $this->createMock(SettingsFormHandler::class);
        $settingsFormHandler->expects(self::once())->method('handle')->with($form)->willReturn(true);

        $instanceService = $this->createMock(InstanceService::class);
        $instanceService->expects(self::never())->method('buildSettingsViewData');

        $controller = new TestInstanceController($instanceService, $form);
        $response = $controller->settings($request, $availabilityService, $settingsFormHandler);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/home', $response->getTargetUrl());
    }

    public function testAccessRendersUsersViewDataDuringInitializationWithoutJwt(): void
    {
        $request = Request::create('/access');
        $request->attributes->set('InstanceRegistration', true);
        $formView = new FormView();

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('createView')->willReturn($formView);

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('getAvailability')
            ->with($request)
            ->willReturn(new MenuAvailabilityDTO(true, true, true));

        $whitelistedUserHandler = $this->createMock(WhitelistedUserFormHandler::class);
        $whitelistedUserHandler->method('handle')->with($form)->willReturn(false);
        $whitelistedUserHandler->method('getAll')->willReturn([['email' => 'user@example.test']]);

        $instanceService = $this->createMock(InstanceService::class);
        $instanceService
            ->expects(self::once())
            ->method('buildUsersViewData')
            ->with($request, self::isInstanceOf(MenuAvailabilityDTO::class), $formView, [['email' => 'user@example.test']])
            ->willReturn(new InstanceUsersViewDataDTO(
                true,
                new MenuAvailabilityDTO(true, true, true),
                $formView,
                [['email' => 'user@example.test']]
            ));

        $controller = new TestInstanceController($instanceService, $form);
        $response = $controller->access($request, $availabilityService, $whitelistedUserHandler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/containers/container-users.html.twig', $controller->renderedTemplate);
        self::assertSame('user@example.test', $controller->renderedParameters['whitelistedUsers'][0]['email']);
    }

    public function testAccessRemainsAvailableWithoutJwtWhenInitializationIsActive(): void
    {
        $request = Request::create('/access');
        $request->attributes->set('InstanceRegistration', true);
        $formView = new FormView();

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('createView')->willReturn($formView);

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('getAvailability')
            ->with($request)
            ->willReturn(new MenuAvailabilityDTO(true, true, true));

        $whitelistedUserHandler = $this->createMock(WhitelistedUserFormHandler::class);
        $whitelistedUserHandler->method('handle')->with($form)->willReturn(false);
        $whitelistedUserHandler->method('getAll')->willReturn([]);

        $instanceService = $this->createMock(InstanceService::class);
        $instanceService
            ->expects(self::once())
            ->method('buildUsersViewData')
            ->willReturn(new InstanceUsersViewDataDTO(
                false,
                new MenuAvailabilityDTO(true, true, true),
                $formView,
                []
            ));

        $controller = new TestInstanceController($instanceService, $form);
        $response = $controller->access($request, $availabilityService, $whitelistedUserHandler);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testAccessRedirectsWhenWhitelistFormHandlerSucceeds(): void
    {
        $request = Request::create('/access');
        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService->expects(self::once())->method('getAvailability')->with($request)->willReturn(new MenuAvailabilityDTO(true, true, true));

        $whitelistedUserHandler = $this->createMock(WhitelistedUserFormHandler::class);
        $whitelistedUserHandler->expects(self::once())->method('handle')->with($form)->willReturn(true);
        $whitelistedUserHandler->expects(self::never())->method('getAll');

        $instanceService = $this->createMock(InstanceService::class);
        $instanceService->expects(self::never())->method('buildUsersViewData');

        $controller = new TestInstanceController($instanceService, $form);
        $response = $controller->access($request, $availabilityService, $whitelistedUserHandler);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/access', $response->getTargetUrl());
    }

    public function testUpdateWhitelistedUserStatusDoesNotBodyEnforceCsrfAnymore(): void
    {
        $request = Request::create('/access/5/status', 'POST', [
            '_token' => 'invalid-token',
            'active' => '1',
        ]);
        $request->attributes->set('InstanceRegistration', true);

        $whitelistedUserHandler = $this->createMock(WhitelistedUserFormHandler::class);
        $whitelistedUserHandler->expects(self::once())->method('updateStatus')->with(5, true);

        $controller = new TestInstanceController($this->createMock(InstanceService::class), $this->createMock(FormInterface::class));

        $response = $controller->updateWhitelistedUserStatus(5, $request, $whitelistedUserHandler);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/access', $response->getTargetUrl());
    }

    public function testDeleteWhitelistedUserDoesNotBodyEnforceCsrfAnymore(): void
    {
        $request = Request::create('/access/5/delete', 'POST', [
            '_token' => 'invalid-token',
        ]);
        $request->attributes->set('InstanceRegistration', true);

        $whitelistedUserHandler = $this->createMock(WhitelistedUserFormHandler::class);
        $whitelistedUserHandler->expects(self::once())->method('delete')->with(5);

        $controller = new TestInstanceController($this->createMock(InstanceService::class), $this->createMock(FormInterface::class));

        $response = $controller->deleteWhitelistedUser(5, $request, $whitelistedUserHandler);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/access', $response->getTargetUrl());
    }
}

final class TestInstanceController extends InstanceController
{
    public string $renderedTemplate = '';
    public array $renderedParameters = [];

    public function __construct(InstanceService $instanceService, private FormInterface $form)
    {
        parent::__construct($instanceService);
    }

    protected function createForm(string $type, mixed $data = null, array $options = []): FormInterface
    {
        return $this->form;
    }

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
