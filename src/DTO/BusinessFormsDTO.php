<?php

namespace App\DTO;

use Symfony\Component\Form\FormInterface;

class BusinessFormsDTO
{
    public function __construct(
        public FormInterface $pswManager,
        public FormInterface $biometric,
        public FormInterface $businessBasic,
        public FormInterface $businessPlus,
        public FormInterface $businessPro
    ) {
    }

    public function all(): array
    {
        return [
            'pswManager' => $this->pswManager,
            'biometric' => $this->biometric,
            'businessBasic' => $this->businessBasic,
            'businessPlus' => $this->businessPlus,
            'businessPro' => $this->businessPro,
        ];
    }
}
