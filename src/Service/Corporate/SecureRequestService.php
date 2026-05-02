<?php

namespace App\Service\Corporate;

use Symfony\Component\HttpFoundation\JsonResponse;

class SecureRequestService
{
    public function __construct(
        private RequestIdentityService $requestIdentityService,
        private AuthorizedBackendResponseService $authorizedBackendResponseService,
        private SecureBackendClient $secureBackendClient
    ) {
    }

    public function generateRequestIdentity(): string
    {
        return $this->requestIdentityService->generate();
    }

    public function decodeAuthorizedResponse(JsonResponse $response): array
    {
        return $this->authorizedBackendResponseService->decode($response);
    }

    public function postSecure(array $dataIntegrity): JsonResponse
    {
        return $this->secureBackendClient->post($dataIntegrity);
    }

    public function postSecureAndDecode(array $dataIntegrity): array
    {
        return $this->decodeAuthorizedResponse($this->postSecure($dataIntegrity));
    }
}
