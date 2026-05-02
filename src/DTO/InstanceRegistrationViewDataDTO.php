<?php

namespace App\DTO;

use Symfony\Component\Form\FormView;

class InstanceRegistrationViewDataDTO
{
    public function __construct(
        public FormView $formIdentityRequester,
        public ?array $serviceAuthData,
        public string $path,
        public MenuAvailabilityDTO $availabilities
    ) {
    }

    public function toArray(): array
    {
        return [
            'form_identity_requester' => $this->formIdentityRequester,
            'service_auth_data' => $this->serviceAuthData,
            'path' => $this->path,
            'availabilities' => $this->availabilities->toArray(),
        ];
    }
}
