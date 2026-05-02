<?php

namespace App\DTO;

class CorporateIdentificationDTO
{
    public ?string $publicId = null;
    public ?string $domain = null;
    public string $hmac = '';
    public ?string $userPublicId = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->publicId = isset($data['publicId']) ? (string) $data['publicId'] : null;
        $dto->domain = isset($data['domain']) ? (string) $data['domain'] : null;
        $dto->hmac = (string) ($data['hmac'] ?? '');
        $dto->userPublicId = isset($data['userPublicId']) ? (string) $data['userPublicId'] : null;

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'domain' => $this->domain,
            'hmac' => $this->hmac,
            'userPublicId' => $this->userPublicId,
        ];
    }
}
