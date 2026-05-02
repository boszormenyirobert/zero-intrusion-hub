<?php

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class CsrfProtectedRoute
{
    public function __construct(
        public readonly ?string $reason = null,
        public readonly ?string $tokenId = null,
        public readonly ?string $failureRoute = null,
        public readonly string $tokenField = '_token',
        public readonly string $tokenSource = 'request',
        public readonly string $failureMessage = 'Invalid CSRF token.',
    ) {
    }
}
