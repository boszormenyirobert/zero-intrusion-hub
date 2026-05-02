<?php

namespace App\DTO;

class AuthenticatedUserDTO
{
    public string $publicId = '';
    public string $email = '';

    public function __construct(string $publicId = '', string $email = '')
    {
        $this->publicId = $publicId;
        $this->email = $email;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['publicId'] ?? $data['userPublicId'] ?? ''),
            (string) ($data['email'] ?? $data['userEmail'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'email' => $this->email,
        ];
    }

    public function toTemplateArray(): array
    {
        return [
            'userPublicId' => $this->publicId,
            'userEmail' => $this->email,
        ];
    }
}
