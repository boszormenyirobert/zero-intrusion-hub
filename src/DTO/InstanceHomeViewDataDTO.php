<?php

namespace App\DTO;

class InstanceHomeViewDataDTO
{
    public function __construct(
        public bool $isJwtValid,
        public AuthenticatedUserDTO $user,
        public MenuAvailabilityDTO $availabilities
    ) {
    }

    public function toArray(): array
    {
        return [
            'is_jwt_valid' => $this->isJwtValid,
            'user' => $this->user->toTemplateArray(),
            'availabilities' => $this->availabilities->toArray(),
        ];
    }
}
