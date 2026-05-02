<?php

namespace App\DTO;

class LoginRequestDTO
{
    public function __construct(
        public ?string $userPublicId = null
    ) {
    }
}
