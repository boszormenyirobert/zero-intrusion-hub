<?php

namespace App\DTO;

class JwtContextDTO
{
    public bool $isJwtValid = false;
    public string $userPublicId = '';
    public string $userEmail = '';
    public ?array $payload = null;

    public function __construct(
        bool $isJwtValid = false,
        string $userPublicId = '',
        string $userEmail = '',
        ?array $payload = null
    ) {
        $this->isJwtValid = $isJwtValid;
        $this->userPublicId = $userPublicId;
        $this->userEmail = $userEmail;
        $this->payload = $payload;
    }

    public static function invalid(): self
    {
        return new self();
    }

    public function toArray(): array
    {
        return [
            'isJwtValid' => $this->isJwtValid,
            'userPublicId' => $this->userPublicId,
            'userEmail' => $this->userEmail,
            'payload' => $this->payload,
        ];
    }

    public function toUserDto(): AuthenticatedUserDTO
    {
        return new AuthenticatedUserDTO($this->userPublicId, $this->userEmail);
    }
}
