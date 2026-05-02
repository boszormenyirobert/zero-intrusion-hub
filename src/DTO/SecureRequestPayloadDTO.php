<?php

namespace App\DTO;

class SecureRequestPayloadDTO
{
    public function __construct(
        public string $corporatePublicId,
        public string $corporateAuthentication,
        public string $domain,
        public ?string $userPublicId = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'corporatePublicId' => $this->corporatePublicId,
            'corporateAuthentication' => $this->corporateAuthentication,
            'domain' => $this->domain,
            'userPublicId' => $this->userPublicId,
        ];
    }
}
