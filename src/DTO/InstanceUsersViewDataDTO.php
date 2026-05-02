<?php

namespace App\DTO;

use Symfony\Component\Form\FormView;

class InstanceUsersViewDataDTO
{
    public function __construct(
        public bool $isJwtValid,
        public MenuAvailabilityDTO $availabilities,
        public FormView $form,
        public array $whitelistedUsers
    ) {
    }

    public function toArray(): array
    {
        return [
            'is_jwt_valid' => $this->isJwtValid,
            'availabilities' => $this->availabilities->toArray(),
            'form' => $this->form,
            'whitelistedUsers' => $this->whitelistedUsers,
        ];
    }
}
