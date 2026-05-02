<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ClientAuthRequestResolver
{
    public function __construct(
        private ApiClientAuthGuard $apiClientAuthGuard
    ) {
    }

    public function resolveOrDeny(Request $request): string|JsonResponse
    {
        $header = $this->apiClientAuthGuard->resolveHeader($request);

        if ($header === null) {
            return $this->apiClientAuthGuard->createMissingHeaderResponse();
        }

        return $header;
    }
}
