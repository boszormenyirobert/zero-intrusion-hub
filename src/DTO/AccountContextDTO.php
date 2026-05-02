<?php

namespace App\DTO;

class AccountContextDTO
{
    public function __construct(
        public JwtContextDTO $jwt,
        public AuthenticatedUserDTO $user
    ) {
    }
}
