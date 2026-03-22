<?php

namespace App\Service\User;

use App\Service\Corporate\AuthorizationControllService;
use \Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;

class UserRegistrationService
{
    public function __construct(
        private AuthorizationControllService $authorizationControllService,
        private LoggerInterface $logger
    ) {}

    /**
     * Forwards registration data securely to the authorization service.
     * Returns the response from the backend.
     */
    public function forwardRegistration(array $data): JsonResponse
    {
        $response = $this->authorizationControllService->getSecurePostRequest($data);

        return $response;
    }
}
