<?php

namespace App\DTO;

class QrCodeResponseDTO
{
    private array $raw = [];

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->raw = $data;

        return $dto;
    }

    public function toArray(): array
    {
        return $this->raw;
    }

    public function getRegistrationProcessId(): ?string
    {
        return isset($this->raw['registrationProcessId']) ? (string) $this->raw['registrationProcessId'] : null;
    }

    public function getDomainProcessId(): ?string
    {
        return isset($this->raw['domainProcessId']) ? (string) $this->raw['domainProcessId'] : null;
    }

    public function getQrCode(): ?string
    {
        return isset($this->raw['qrCode']) ? (string) $this->raw['qrCode'] : null;
    }

    public function hasLoginFields(): bool
    {
        return $this->getDomainProcessId() !== null && $this->getQrCode() !== null;
    }
}
