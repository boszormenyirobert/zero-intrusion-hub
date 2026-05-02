<?php

namespace App\Service\User;

use App\Service\Corporate\SecureRequestService;
use Symfony\Component\HttpFoundation\JsonResponse;

class BackendForwardingService
{
    public function __construct(
        private SecureRequestService $secureRequestService
    ) {
    }

    public function forwardRegistration(array $data): JsonResponse
    {
        return $this->secureRequestService->postSecure($data);
    }
}
