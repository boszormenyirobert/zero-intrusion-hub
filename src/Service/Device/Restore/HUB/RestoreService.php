<?php

namespace App\Service\Device\Restore\HUB;

use App\DTO\ReplaceDeviceDTO;
use App\DTO\ReplaceDevicePinDTO;
use App\DTO\ReplaceDevicePinViewDataDTO;
use App\DTO\ReplaceDeviceViewDataDTO;
use App\DTO\RestorePinRequestDTO;
use App\Service\Device\ReplaceDeviceService;
use App\Service\Qr\GenerateQrService;
use App\Service\Shared\ProcessKey;
use App\Service\User\BackendForwardingService;
use Symfony\Component\Form\FormView;

class RestoreService
{
    public function __construct(
        private ReplaceDeviceService $replaceDeviceService,
        private GenerateQrService $generateQrService
    ) {
    }

    public function submitReplaceDevice(ReplaceDeviceDTO $data, BackendForwardingService $backendForwardingService): void
    {
        $backendForwardingService->forwardRegistration([
            ProcessKey::REPLACE_DEVICE => $data->toArray(),
        ]);
    }

    public function resolveReplaceDevicePinQrCode(string $replaceHash, ReplaceDevicePinDTO $pinData): ?string
    {
        $request = new RestorePinRequestDTO($pinData, $replaceHash);
        $response = $this->replaceDeviceService->forwardRegistration([
            ProcessKey::RESTORE_PIN => $request->toArray(),
        ]);

        if (!$this->replaceDeviceService->validateResponse($response)) {
            return null;
        }

        return $this->generateQrService->getQrCode($response->toQrPayload());
    }

    public function buildReplaceViewData(FormView $formView): ReplaceDeviceViewDataDTO
    {
        return new ReplaceDeviceViewDataDTO($formView);
    }

    public function buildReplacePinViewData(FormView $formView, string $replaceHash, ?string $qrCodeData): ReplaceDevicePinViewDataDTO
    {
        return new ReplaceDevicePinViewDataDTO($formView, $replaceHash, $qrCodeData);
    }
}
