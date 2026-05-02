<?php

namespace App\DTO;

class ReplaceDeviceDTO
{
    public string $email = '';
    public string $phone = '';

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
