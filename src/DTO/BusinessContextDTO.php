<?php

namespace App\DTO;

class BusinessContextDTO
{
    public function __construct(
        public JwtContextDTO $jwt,
        public AuthenticatedUserDTO $user
    ) {
    }
}
