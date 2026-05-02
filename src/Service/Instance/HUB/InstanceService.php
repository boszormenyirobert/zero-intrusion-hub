<?php

namespace App\Service\Instance\HUB;

use App\DTO\InstanceHomeViewDataDTO;
use App\DTO\InstanceSettingsViewDataDTO;
use App\DTO\InstanceUsersViewDataDTO;
use App\DTO\JwtContextDTO;
use App\DTO\MenuAvailabilityDTO;
use App\Service\Instance\HUB\JwtContextService;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;

class InstanceService
{
    public function __construct(
        private JwtContextService $jwtContextService,
        private RegistrationMenuAvailabilityService $registrationMenuAvailabilityService
    ) {
    }

    public function buildHomeViewData(Request $request): InstanceHomeViewDataDTO
    {
        $jwtContext = $this->buildJwtContext($request);
        $availabilities = $this->registrationMenuAvailabilityService->getAvailability($request);

        return new InstanceHomeViewDataDTO(
            $jwtContext->isJwtValid,
            $jwtContext->toUserDto(),
            $availabilities
        );
    }

    public function buildSettingsViewData(Request $request, MenuAvailabilityDTO $availabilities, FormView $form): InstanceSettingsViewDataDTO
    {
        $jwtContext = $this->buildJwtContext($request);

        return new InstanceSettingsViewDataDTO(
            $jwtContext->isJwtValid,
            $jwtContext->toUserDto(),
            $availabilities,
            $form
        );
    }

    public function buildUsersViewData(Request $request, MenuAvailabilityDTO $availabilities, FormView $form, array $whitelistedUsers): InstanceUsersViewDataDTO
    {
        $jwtContext = $this->buildJwtContext($request);

        return new InstanceUsersViewDataDTO(
            $jwtContext->isJwtValid,
            $availabilities,
            $form,
            $whitelistedUsers,
        );
    }

    public function buildJwtContext(Request $request): JwtContextDTO
    {
        return $this->jwtContextService->build($request);
    }
}
