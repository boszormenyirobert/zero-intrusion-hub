<?php

namespace App\Service\User\Registration\HUB;

use App\Controller\User\UserService;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class RegistrationService
{
    public function __construct(
        private LoggerInterface $logger,
        private UserService $userService,
        private RegistrationMenuAvailabilityService $registrationMenuAvailabilityService
    ) {}

    public function buildRegistrationViewData(Request $request): array
    {
        $response = $this->userService->getQrCode('user_registration', []);
        $this->logger->info('Registration QR code generated', [
            'route' => $request->attributes->get('_route'),
            'process' => 'user_registration',
            'registration_process_id' => $response['registrationProcessId'] ?? null,
        ]);

        $availabilities = $this->registrationMenuAvailabilityService->getAvailability($request);

        return [
            'qrCode' => $response,
            'menuItem_instanceRegistration' => true,
            'processId' => $response['registrationProcessId'] ?? null,
            'action' => 'registration',
            'availabilities' => $availabilities
        ];
    }
}