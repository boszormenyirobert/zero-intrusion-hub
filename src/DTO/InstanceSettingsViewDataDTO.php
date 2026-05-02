<?php

namespace App\DTO;

use Symfony\Component\Form\FormView;

class InstanceSettingsViewDataDTO
{
    public function __construct(
        public bool $isJwtValid,
        public AuthenticatedUserDTO $user,
        public MenuAvailabilityDTO $availabilities,
        public FormView $form
    ) {
    }

    public function toArray(): array
    {
        return [
            'is_jwt_valid' => $this->isJwtValid,
            'user' => $this->user->toTemplateArray(),
            'availabilities' => $this->availabilities->toArray(),
            'form' => $this->form,
        ];
    }
}
