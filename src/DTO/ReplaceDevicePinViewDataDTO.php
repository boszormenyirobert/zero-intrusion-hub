<?php

namespace App\DTO;

use Symfony\Component\Form\FormView;

class ReplaceDevicePinViewDataDTO
{
    public function __construct(
        public FormView $replaceDevicePinForm,
        public string $replaceHash,
        public ?string $qrCodeData = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'replace_device_pin' => $this->replaceDevicePinForm,
            'replaceHash' => $this->replaceHash,
            'qrCodeData' => $this->qrCodeData,
        ];
    }
}
