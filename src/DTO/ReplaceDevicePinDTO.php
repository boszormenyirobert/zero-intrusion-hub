<?php

namespace App\DTO;

class ReplaceDevicePinDTO
{
    public string $pin = '';

    public function toArray(): array
    {
        return [
            'pin' => $this->pin,
        ];
    }
}
