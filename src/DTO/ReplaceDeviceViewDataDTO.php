<?php

namespace App\DTO;

use Symfony\Component\Form\FormView;

class ReplaceDeviceViewDataDTO
{
    public function __construct(
        public FormView $replaceDeviceForm
    ) {
    }

    public function toArray(): array
    {
        return [
            'replace_device' => $this->replaceDeviceForm,
        ];
    }
}
