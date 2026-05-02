<?php

namespace App\DTO;

class SecureRequestIdentityDTO
{
    public function __construct(
        public ?string $publicId,
        public ?string $domain,
        public string $hmac
    ) {
    }
}
