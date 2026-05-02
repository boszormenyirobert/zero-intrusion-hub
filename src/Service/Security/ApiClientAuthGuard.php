<?php

namespace App\Service\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ApiClientAuthGuard
{
    public const HEADER_NAME = 'x-client-auth';
    public const REQUEST_ATTRIBUTE = '_client_auth_header';
    private const ERROR_MISSING_CLIENT_AUTH = 'Missing X-Client-Auth header!';

    /**
     * Resolves the client-auth header used by protected proxy routes.
     *
     * This guard does not perform full cryptographic validation of client-facing
     * HMAC values that may have been issued by the upstream API. In those flows,
     * the HUB acts as a policy-enforcing proxy and the upstream API remains the
     * final validation authority for the returned HMAC material.
     */

    public function resolveHeader(Request $request): ?string
    {
        $requestAttributeHeader = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if ($this->isValidHeaderValue($requestAttributeHeader)) {
            return trim($requestAttributeHeader);
        }

        $header = $request->headers->get(self::HEADER_NAME);

        return $this->isValidHeaderValue($header) ? trim($header) : null;
    }

    public function storeValidatedHeader(Request $request, string $header): void
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, trim($header));
    }

    public function createMissingHeaderResponse(): JsonResponse
    {
        return new JsonResponse(['error' => self::ERROR_MISSING_CLIENT_AUTH], 401);
    }

    private function isValidHeaderValue(mixed $header): bool
    {
        return is_string($header) && trim($header) !== '';
    }
}
