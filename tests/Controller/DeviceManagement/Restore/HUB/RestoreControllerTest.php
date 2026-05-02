<?php

declare(strict_types=1);

namespace App\Tests\Controller\DeviceManagement\Restore\HUB;

use App\Attribute\PublicRoute;
use App\Controller\DeviceManagement\Restore\HUB\RestoreController;
use App\DTO\ReplaceDeviceDTO;
use App\DTO\ReplaceDevicePinDTO;
use App\DTO\ReplaceDevicePinViewDataDTO;
use App\DTO\ReplaceDeviceViewDataDTO;
use App\Service\Device\Restore\HUB\RestoreService;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RestoreControllerTest extends TestCase
{
    public function testReplaceDeviceRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(RestoreController::class, 'replaceDevice');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testReplaceDevicePinRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(RestoreController::class, 'replaceDevicePin');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testReplaceDeviceSubmitsTypedPayloadAndRendersViewData(): void
    {
        $request = Request::create('/replace-device', 'POST');
        $formView = new FormView();
        $formData = new ReplaceDeviceDTO();
        $formData->email = 'user@example.test';
        $formData->phone = '+3612345678';

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($formData);
        $form->method('createView')->willReturn($formView);

        $restoreService = $this->createMock(RestoreService::class);
        $restoreService
            ->expects(self::once())
            ->method('submitReplaceDevice')
            ->with($formData, self::isInstanceOf(BackendForwardingService::class));
        $restoreService
            ->expects(self::once())
            ->method('buildReplaceViewData')
            ->with($formView)
            ->willReturn(new ReplaceDeviceViewDataDTO($formView));

        $controller = new TestRestoreController($restoreService, $form);
        $response = $controller->replaceDevice($request, $this->createMock(BackendForwardingService::class));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/device/replace.html.twig', $controller->renderedTemplate);
        self::assertArrayHasKey('replace_device', $controller->renderedParameters);
    }

    public function testReplaceDevicePinBuildsQrCodeViewData(): void
    {
        $request = Request::create('/replace-device/hash-123', 'POST');
        $formView = new FormView();
        $pinData = new ReplaceDevicePinDTO();
        $pinData->pin = '1234';

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request)->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($pinData);
        $form->method('createView')->willReturn($formView);

        $restoreService = $this->createMock(RestoreService::class);
        $restoreService
            ->expects(self::once())
            ->method('resolveReplaceDevicePinQrCode')
            ->with('hash-123', $pinData)
            ->willReturn('qr-code-data');
        $restoreService
            ->expects(self::once())
            ->method('buildReplacePinViewData')
            ->with($formView, 'hash-123', 'qr-code-data')
            ->willReturn(new ReplaceDevicePinViewDataDTO($formView, 'hash-123', 'qr-code-data'));

        $controller = new TestRestoreController($restoreService, $form);
        $response = $controller->replaceDevicePin('hash-123', $request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/device/replacePin.html.twig', $controller->renderedTemplate);
        self::assertSame('hash-123', $controller->renderedParameters['replaceHash']);
        self::assertSame('qr-code-data', $controller->renderedParameters['qrCodeData']);
    }
}

final class TestRestoreController extends RestoreController
{
    public string $renderedTemplate = '';
    public array $renderedParameters = [];

    public function __construct(RestoreService $restoreService, private FormInterface $form)
    {
        parent::__construct($restoreService);
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
}