<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Restore\HUB;

use App\DTO\ReplaceDeviceDTO;
use App\DTO\ReplaceDevicePinDTO;
use App\DTO\ReplaceDeviceResultDTO;
use App\Service\Device\ReplaceDeviceService;
use App\Service\Device\Restore\HUB\RestoreService;
use App\Service\Qr\GenerateQrService;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

final class RestoreServiceTest extends TestCase
{
    public function testSubmitReplaceDeviceForwardsTypedPayload(): void
    {
        $request = new ReplaceDeviceDTO();
        $request->email = 'user@example.test';
        $request->phone = '+3612345678';

        $backendForwardingService = $this->createMock(BackendForwardingService::class);
        $backendForwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'replaceDevice' => [
                    'email' => 'user@example.test',
                    'phone' => '+3612345678',
                ],
            ]);

        $service = new RestoreService(
            $this->createMock(ReplaceDeviceService::class),
            $this->createMock(GenerateQrService::class)
        );

        $service->submitReplaceDevice($request, $backendForwardingService);
    }

    public function testResolveReplaceDevicePinQrCodeReturnsNullWhenBackendResponseIsInvalid(): void
    {
        $pinRequest = new ReplaceDevicePinDTO();
        $pinRequest->pin = '1234';

        $replaceDeviceService = $this->createMock(ReplaceDeviceService::class);
        $replaceDeviceService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'restorePin' => [
                    'data' => ['pin' => '1234'],
                    'replaceHash' => 'replace-hash',
                ],
            ])
            ->willReturn(new ReplaceDeviceResultDTO());
        $replaceDeviceService
            ->expects(self::once())
            ->method('validateResponse')
            ->willReturn(false);

        $generateQrService = $this->createMock(GenerateQrService::class);
        $generateQrService
            ->expects(self::never())
            ->method('getQrCode');

        $service = new RestoreService($replaceDeviceService, $generateQrService);

        self::assertNull($service->resolveReplaceDevicePinQrCode('replace-hash', $pinRequest));
    }

    public function testResolveReplaceDevicePinQrCodeBuildsQrPayloadForValidResponse(): void
    {
        $pinRequest = new ReplaceDevicePinDTO();
        $pinRequest->pin = '1234';

        $replaceResult = ReplaceDeviceResultDTO::fromArray([
            'publicId' => 'public-id',
            'privateId' => 'private-id',
            'secret' => 'secret-value',
        ]);

        $replaceDeviceService = $this->createMock(ReplaceDeviceService::class);
        $replaceDeviceService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->willReturn($replaceResult);
        $replaceDeviceService
            ->expects(self::once())
            ->method('validateResponse')
            ->with($replaceResult)
            ->willReturn(true);

        $generateQrService = $this->createMock(GenerateQrService::class);
        $generateQrService
            ->expects(self::once())
            ->method('getQrCode')
            ->with([
                'publicId' => 'public-id',
                'privateId' => 'private-id',
                'secret' => 'secret-value',
                'type' => 'recovery',
                'source' => 'easyPublic',
            ])
            ->willReturn('qr-code-data');

        $service = new RestoreService($replaceDeviceService, $generateQrService);

        self::assertSame('qr-code-data', $service->resolveReplaceDevicePinQrCode('replace-hash', $pinRequest));
    }

    public function testBuildReplaceViewDataWrapsFormView(): void
    {
        $formView = new FormView();
        $service = new RestoreService(
            $this->createMock(ReplaceDeviceService::class),
            $this->createMock(GenerateQrService::class)
        );

        $viewData = $service->buildReplaceViewData($formView);

        self::assertSame($formView, $viewData->replaceDeviceForm);
    }

    public function testBuildReplacePinViewDataWrapsFormViewHashAndQrCode(): void
    {
        $formView = new FormView();
        $service = new RestoreService(
            $this->createMock(ReplaceDeviceService::class),
            $this->createMock(GenerateQrService::class)
        );

        $viewData = $service->buildReplacePinViewData($formView, 'replace-hash', 'qr-code-data');

        self::assertSame($formView, $viewData->replaceDevicePinForm);
        self::assertSame('replace-hash', $viewData->replaceHash);
        self::assertSame('qr-code-data', $viewData->qrCodeData);
    }
}
