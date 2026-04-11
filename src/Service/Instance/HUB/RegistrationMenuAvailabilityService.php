<?php

namespace App\Service\Instance\HUB;

use App\Repository\WhitelistedUsersRepository;
use App\Service\Instance\HUB\JwtContextService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class RegistrationMenuAvailabilityService
{
    public function __construct(
        private JwtContextService $jwtContextService,
        private WhitelistedUsersRepository $whitelistedUsersRepository,
        private LoggerInterface $logger
    ) {}

    public function getAvailability(Request $request): array
    {
        $jwtContext = $this->jwtContextService->build($request);
        $isJwtValid = $jwtContext['isJwtValid'];
        $isInstanceRegistrationEnabled = (bool) $request->get('InstanceRegistration');
        $hasWhitelistedUsers = count($this->whitelistedUsersRepository->findAll()) > 0;
        $usersMenuAvailability = (!$isJwtValid && !$hasWhitelistedUsers) || ($isJwtValid);

        $availability = [
            'availability_settings' => $isInstanceRegistrationEnabled,
            'availability_instance' => $isInstanceRegistrationEnabled,
            'availability_users' => $usersMenuAvailability
        ];

        $this->logger->info('Resolved HUB registration menu availability', [
            'route' => $request->attributes->get('_route'),
            'is_jwt_valid' => $isJwtValid,
            'instance_registration_enabled' => $isInstanceRegistrationEnabled,
            'has_whitelisted_users' => $hasWhitelistedUsers,
            'availability' => $availability,
        ]);

        return $availability;
    }
}