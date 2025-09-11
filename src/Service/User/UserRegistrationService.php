<?php

namespace App\Service\User;

use App\Service\Corporate\AuthorizationControllService;
use Symfony\Component\HttpFoundation\Response;

class UserRegistrationService
{

    public function __construct(
        private AuthorizationControllService $authorizationControllService,
        private \Psr\Log\LoggerInterface $logger
    ) {}

    public function forwardRegistration(array $data)
    {
        $response = $this->authorizationControllService->getSecurePostRequest($data);

        return $response;
    }
}
