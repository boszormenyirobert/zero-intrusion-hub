<?php

namespace App\DTO;

class ReplaceDeviceResultDTO
{
    public string $publicId = '';
    public string $privateId = '';
    public string $secret = '';

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->publicId = (string) ($data['publicId'] ?? '');
        $dto->privateId = (string) ($data['privateId'] ?? '');
        $dto->secret = (string) ($data['secret'] ?? '');

        return $dto;
    }

    public function isValid(): bool
    {
        return $this->publicId !== '' && $this->privateId !== '' && $this->secret !== '';
    }

    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'privateId' => $this->privateId,
            'secret' => $this->secret,
        ];
    }

    public function toQrPayload(): array
    {
        return [
            'publicId' => $this->publicId,
            'privateId' => $this->privateId,
            'secret' => $this->secret,
            'type' => 'recovery',
            'source' => 'easyPublic',
        ];
    }
}
