<?php

namespace App\Service\Instance\HUB;

use App\DTO\MenuAvailabilityDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class RegistrationMenuAvailabilityService
{
    public function __construct(
        private InstanceRegistrationService $instanceRegistrationService,
        private JwtContextService $jwtContextService,
        private LoggerInterface $logger
    ) {
    }

    public function getAvailability(Request $request): MenuAvailabilityDTO
    {
        $isInstanceRegistrationEnabled = $this->isInstanceRegistrationEnabled($request);

        // Access matrix:
        // - initialization = true  => settings open, instance open, users open
        // - initialization = false => settings closed, instance closed, users require valid JWT
        if ($isInstanceRegistrationEnabled) {
            $availability = new MenuAvailabilityDTO(true, true, true);

            $this->logger->info('Resolved HUB registration menu availability', [
                'route' => $request->attributes->get('_route'),
                'is_jwt_valid' => null,
                'instance_registration_enabled' => true,
                'has_whitelisted_users' => null,
                'availability' => $availability->toArray(),
            ]);

            return $availability;
        }

        $jwtContext = $this->jwtContextService->build($request);

        $availability = new MenuAvailabilityDTO(
            false,
            false,
            $jwtContext->isJwtValid
        );

        $this->logger->info('Resolved HUB registration menu availability', [
            'route' => $request->attributes->get('_route'),
            'is_jwt_valid' => $jwtContext->isJwtValid,
            'instance_registration_enabled' => $isInstanceRegistrationEnabled,
            'has_whitelisted_users' => null,
            'availability' => $availability->toArray(),
        ]);

        return $availability;
    }

    /**
     * The settings route is only available while initialization is active.
     */
    public function canAccessManagementRoute(Request $request): bool
    {
        // The settings route is only available during the initialization phase.
        if ($this->isInstanceRegistrationEnabled($request)) {
            return true;
        }

        return false;
    }

    /**
     * The users/access routes remain open during initialization.
     * After initialization is finished, they require a valid JWT.
     */
    public function canAccessUsersRoute(Request $request): bool
    {
        // The users/access routes stay open during initialization.
        // Once initialization is finished, they must require a valid JWT.
        if ($this->isInstanceRegistrationEnabled($request)) {
            return true;
        }

        return $this->jwtContextService->build($request)->isJwtValid;
    }

    public function isExternalInstanceRegistrationAvailable(Request $request): bool
    {
        $isJwtValid = $this->jwtContextService->build($request)->isJwtValid;

        $this->logger->info('Resolved external HUB instance registration availability', [
            'route' => $request->attributes->get('_route'),
            'is_jwt_valid' => $isJwtValid,
        ]);

        return $isJwtValid;
    }

    public function isInstanceRegistrationFollowUpAvailable(Request $request): bool
    {
        $isJwtValid = $this->jwtContextService->build($request)->isJwtValid;

        $this->logger->info('Resolved HUB instance registration follow-up availability', [
            'route' => $request->attributes->get('_route'),
            'is_jwt_valid' => $isJwtValid,
        ]);

        return $isJwtValid;
    }

    private function isInstanceRegistrationEnabled(Request $request): bool
    {
        $isEnabled = $this->instanceRegistrationService->getInitializationState();

        $this->logger->debug('Resolved HUB instance registration state from authoritative source', [
            'route' => $request->attributes->get('_route'),
            'instance_registration_enabled' => $isEnabled,
        ]);

        return $isEnabled;
    }
}
