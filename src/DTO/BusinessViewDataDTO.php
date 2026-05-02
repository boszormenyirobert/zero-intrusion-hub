<?php

namespace App\DTO;

use Symfony\Component\Form\FormView;

class BusinessViewDataDTO
{
    public function __construct(
        public bool $isJwtValid,
        public AuthenticatedUserDTO $user,
        public FormView|string $formPswManager,
        public FormView|string $formBiometric,
        public FormView|string $formBusinessBasic,
        public FormView|string $formBusinessPlus,
        public FormView|string $formBusinessPro,
        public ?array $serviceAuthData,
        public bool $menuItemInstanceRegistration
    ) {
    }

    public function toArray(): array
    {
        return [
            'is_jwt_valid' => $this->isJwtValid,
            'user' => $this->user->toTemplateArray(),
            'form_psw_manager' => $this->formPswManager,
            'form_biometric' => $this->formBiometric,
            'form_business_basic' => $this->formBusinessBasic,
            'form_business_plus' => $this->formBusinessPlus,
            'form_business_pro' => $this->formBusinessPro,
            'service_auth_data' => $this->serviceAuthData,
            'menuItem_instanceRegistration' => $this->menuItemInstanceRegistration,
        ];
    }
}
