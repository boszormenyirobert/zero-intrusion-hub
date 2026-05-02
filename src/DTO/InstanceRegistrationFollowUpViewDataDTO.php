<?php

namespace App\DTO;

use Symfony\Component\Form\FormView;

class InstanceRegistrationFollowUpViewDataDTO
{
    public function __construct(
        public FormView $formIdentityFollowUp,
        public MenuAvailabilityDTO $availabilities
    ) {
    }

    public function toArray(): array
    {
        return [
            'form_identity_followup' => $this->formIdentityFollowUp,
            'availabilities' => $this->availabilities->toArray(),
        ];
    }
}
