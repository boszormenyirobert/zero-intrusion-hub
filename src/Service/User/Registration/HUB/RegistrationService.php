<?php

namespace App\Service\User\Registration\HUB;

use App\DTO\QrCodeResponseDTO;
use App\DTO\RegistrationViewDataDTO;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use App\Service\Shared\ProcessKey;
use App\Service\User\UserQrCodeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class RegistrationService
{
    public function __construct(
        private LoggerInterface $logger,
        private UserQrCodeService $userQrCodeService,
        private RegistrationMenuAvailabilityService $registrationMenuAvailabilityService
    ) {
    }

    public function buildRegistrationViewData(Request $request): RegistrationViewDataDTO
    {
        $response = $this->userQrCodeService->getQrCode(ProcessKey::USER_REGISTRATION);
        $this->logger->info('Registration QR code generated', [
            'route' => $request->attributes->get('_route'),
            'process' => ProcessKey::USER_REGISTRATION,
            'registration_process_id' => $response->getRegistrationProcessId(),
        ]);

        $availabilities = $this->registrationMenuAvailabilityService->getAvailability($request);

        return new RegistrationViewDataDTO(
            $response,
            true,
            'registration',
            $availabilities
        );
    }
}
