<?php

namespace App\Service\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ExtensionAuthGuard
{
    public const HEADER_NAME = 'x-extension-auth';
    public const REQUEST_ATTRIBUTE = '_extension_auth_header';
    private const ERROR_MISSING_EXTENSION_AUTH = 'Missing X-Extension-Auth header!';

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
        return new JsonResponse(['error' => self::ERROR_MISSING_EXTENSION_AUTH], 401);
    }

    private function isValidHeaderValue(mixed $header): bool
    {
        return is_string($header) && trim($header) !== '';
    }
}
