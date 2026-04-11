<?php

namespace App\Service\Instance\HUB;

use App\Service\Instance\HUB\JwtContextService;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;

class InstanceService
{
    public function __construct(
        private JwtContextService $jwtContextService,
        private RegistrationMenuAvailabilityService $registrationMenuAvailabilityService
    ) {}

    public function buildHomeViewData(Request $request): array
    {
        $jwtContext = $this->buildJwtContext($request);
        $availiabilites = $this->registrationMenuAvailabilityService->getAvailability($request);

        return [
            'is_jwt_valid' => $jwtContext['isJwtValid'],
            'user' => [
                'userPublicId' => $jwtContext['userPublicId'],
                'userEmail' => $jwtContext['userEmail'],
            ],
            'availabilities' => $availiabilites
        ];
    }

    public function buildSettingsViewData(Request $request, array $availiabilites, FormView $form): array
    {
        $jwtContext = $this->buildJwtContext($request);

        return [
            'is_jwt_valid' => $jwtContext['isJwtValid'],
            'user' => [
                'userPublicId' => $jwtContext['userPublicId'],
                'userEmail' => $jwtContext['userEmail'],
            ],
            'availabilities' => $availiabilites,
            'form' => $form
        ];
    }

    public function buildUsersViewData(Request $request, array $availiabilites, FormView $form): array
    {
        $jwtContext = $this->buildJwtContext($request);

        return [
            'is_jwt_valid' => $jwtContext['isJwtValid'],
            'availabilities' => $availiabilites,
            'form' => $form
        ];
    }

    public function buildJwtContext(Request $request): array
    {
        return $this->jwtContextService->build($request);
    }
}