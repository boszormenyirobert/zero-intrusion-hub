<?php

declare(strict_types=1);

namespace App\Tests\Controller\Instance\HUB;

use App\Attribute\InitializationOnlyRoute;
use App\Attribute\JwtRequired;
use App\Controller\Instance\HUB\RegistrationController;
use App\DTO\BackendPayloadDTO;
use App\DTO\MenuAvailabilityDTO;
use App\Service\Instance\HUB\ExternalInstanceRegistrationHandler;
use App\Service\Instance\HUB\InstanceRegistrationFollowUpHandler;
use App\Service\Instance\HUB\InstanceRegistrationService;
use App\Service\Instance\HUB\InternalInstanceRegistrationHandler;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RegistrationControllerTest extends TestCase
{
    public function testInstanceRegistrationRequiresInitializationOnlyAttribute(): void
    {
        $reflectionMethod = new \ReflectionMethod(RegistrationController::class, 'instanceRegistration');

        self::assertNotEmpty($reflectionMethod->getAttributes(InitializationOnlyRoute::class));
    }

    public function testInstanceRegistrationExternalRequiresJwtAttribute(): void
    {
        $reflectionMethod = new \ReflectionMethod(RegistrationController::class, 'instanceRegistrationExternal');

        self::assertNotEmpty($reflectionMethod->getAttributes(JwtRequired::class));
    }

    public function testInstanceRegistrationFollowUpRequiresJwtAttribute(): void
    {
        $reflectionMethod = new \ReflectionMethod(RegistrationController::class, 'instanceRegistrationFollowUp');

        self::assertNotEmpty($reflectionMethod->getAttributes(JwtRequired::class));
    }

    public function testInstanceRegistrationRendersRegistrationView(): void
    {
        $request = Request::create('/instance-registration');
        $formView = new FormView();

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('createView')->willReturn($formView);

        $internalHandler = $this->createMock(InternalInstanceRegistrationHandler::class);
        $internalHandler
            ->method('handle')
            ->with($form, $request)
            ->willReturn(BackendPayloadDTO::fromArray(['corporate_id' => 'corp-123']));

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->method('getAvailability')
            ->with($request)
            ->willReturn(new MenuAvailabilityDTO(true, true, true));

        $controller = new TestInstanceRegistrationController($form);
        $response = $controller->instanceRegistration(
            $request,
            $internalHandler,
            $availabilityService
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/containers/container-instance-registration.html.twig', $controller->renderedTemplate);
        self::assertSame('corp-123', $controller->renderedParameters['service_auth_data']['corporate_id']);
        self::assertSame('instance_registration', $controller->renderedParameters['path']);
    }

    public function testInstanceRegistrationDoesNotBodyEnforceAvailabilityAnymore(): void
    {
        $request = Request::create('/instance-registration');
        $formView = new FormView();

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('createView')->willReturn($formView);

        $internalHandler = $this->createMock(InternalInstanceRegistrationHandler::class);
        $internalHandler
            ->method('handle')
            ->with($form, $request)
            ->willReturn(null);

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->method('getAvailability')
            ->with($request)
            ->willReturn(new MenuAvailabilityDTO(false, false, false));

        $controller = new TestInstanceRegistrationController($form);
        $response = $controller->instanceRegistration(
            $request,
            $internalHandler,
            $availabilityService
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/containers/container-instance-registration.html.twig', $controller->renderedTemplate);
    }

    public function testInstanceRegistrationExternalRendersCredentialsWhenSubmissionSucceeds(): void
    {
        $request = Request::create('/instance-registration-external');
        $formView = new FormView();

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('createView')->willReturn($formView);

        $externalHandler = $this->createMock(ExternalInstanceRegistrationHandler::class);
        $externalHandler
            ->method('handle')
            ->with($form, $request)
            ->willReturn(BackendPayloadDTO::fromArray([
                'corporate_id' => 'corp-123',
                'corporate_id_key' => 'key-123',
            ]));

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('getAvailability')
            ->with($request)
            ->willReturn(new MenuAvailabilityDTO(false, false, false));

        $controller = new TestInstanceRegistrationController($form);
        $response = $controller->instanceRegistrationExternal(
            $request,
            $externalHandler,
            $availabilityService
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/containers/container-instance-registration.html.twig', $controller->renderedTemplate);
        self::assertSame('instance_registration_external', $controller->renderedParameters['path']);
        self::assertSame('corp-123', $controller->renderedParameters['service_auth_data']['corporate_id']);
    }

    public function testInstanceRegistrationExternalRendersWhenJwtIsValidEvenIfInstanceRegistrationDisabled(): void
    {
        $request = Request::create('/instance-registration-external');
        $formView = new FormView();

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('createView')->willReturn($formView);

        $externalHandler = $this->createMock(ExternalInstanceRegistrationHandler::class);
        $externalHandler->method('handle')->with($form, $request)->willReturn(null);

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('getAvailability')
            ->with($request)
            ->willReturn(new MenuAvailabilityDTO(false, false, false));

        $controller = new TestInstanceRegistrationController($form);
        $response = $controller->instanceRegistrationExternal(
            $request,
            $externalHandler,
            $availabilityService
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/containers/container-instance-registration.html.twig', $controller->renderedTemplate);
        self::assertSame('instance_registration_external', $controller->renderedParameters['path']);
    }

    public function testInstanceRegistrationExternalDoesNotBodyEnforceJwtAvailabilityAnymore(): void
    {
        $request = Request::create('/instance-registration-external');
        $formView = new FormView();

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('createView')->willReturn($formView);

        $externalHandler = $this->createMock(ExternalInstanceRegistrationHandler::class);
        $externalHandler->method('handle')->with($form, $request)->willReturn(null);

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('getAvailability')
            ->with($request)
            ->willReturn(new MenuAvailabilityDTO(false, false, false));

        $controller = new TestInstanceRegistrationController($form);
        $response = $controller->instanceRegistrationExternal(
            $request,
            $externalHandler,
            $availabilityService
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/containers/container-instance-registration.html.twig', $controller->renderedTemplate);
    }

    public function testInstanceRegistrationFollowUpRendersFollowUpView(): void
    {
        $request = Request::create('/instance-registration-follow-up');
        $formView = new FormView();

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('createView')->willReturn($formView);

        $followUpHandler = $this->createMock(InstanceRegistrationFollowUpHandler::class);
        $followUpHandler->method('handle')->with($form)->willReturn(false);

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('getAvailability')
            ->with($request)
            ->willReturn(new MenuAvailabilityDTO(false, false, true));

        $controller = new TestInstanceRegistrationController($form);
        $response = $controller->instanceRegistrationFollowUp($request, $followUpHandler, $availabilityService);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/containers/container-subscription-final.html.twig', $controller->renderedTemplate);
        self::assertArrayHasKey('form_identity_followup', $controller->renderedParameters);
        self::assertSame(false, $controller->renderedParameters['availabilities']['availability_settings']);
    }

    public function testInstanceRegistrationFollowUpDoesNotBodyEnforceJwtAvailabilityAnymore(): void
    {
        $request = Request::create('/instance-registration-follow-up');
        $formView = new FormView();

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('createView')->willReturn($formView);

        $followUpHandler = $this->createMock(InstanceRegistrationFollowUpHandler::class);
        $followUpHandler->method('handle')->with($form)->willReturn(false);

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService
            ->expects(self::once())
            ->method('getAvailability')
            ->with($request)
            ->willReturn(new MenuAvailabilityDTO(false, false, false));

        $controller = new TestInstanceRegistrationController($form);
        $response = $controller->instanceRegistrationFollowUp($request, $followUpHandler, $availabilityService);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/containers/container-subscription-final.html.twig', $controller->renderedTemplate);
    }
}

final class TestInstanceRegistrationController extends RegistrationController
{
    public string $renderedTemplate = '';
    public array $renderedParameters = [];

    public function __construct(private FormInterface $form)
    {
        parent::__construct(new class() extends InstanceRegistrationService {
            public function __construct()
            {
            }
        });
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