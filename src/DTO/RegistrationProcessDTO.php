<?php

namespace App\DTO;

class RegistrationProcessDTO
{
    public string $signature = '';
    public string $publicId = '';
    public string $email = '';
    public string $processId = '';

    public static function mapFromArrayRegistration(array $data): self
    {
        return self::fromMappedValues(
            $data['signature'] ?? '',
            $data['publicId'] ?? '',
            $data['email'] ?? '',
            $data['registrationProcessId'] ?? ''
        );
    }

    public static function mapFromArrayLogin(array $data): self
    {
        return self::fromMappedValues(
            $data['signature'] ?? '',
            $data['publicId'] ?? '',
            $data['email'] ?? '',
            $data['processId'] ?? ''
        );
    }

    public function toArray(): array
    {
        return [
            'signature' => $this->signature,
            'publicId' => $this->publicId,
            'email' => $this->email,
            'processId' => $this->processId,
        ];
    }

    /**
     * Get the value of signature
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * Get the value of publicId
     */
    public function getPublicId(): string
    {
        return $this->publicId;
    }

    /**
     * Get the value of email
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Get the value of processId
     */
    public function getProcessId(): string
    {
        return $this->processId;
    }

    private static function fromMappedValues(string|int|float|bool|null $signature, string|int|float|bool|null $publicId, string|int|float|bool|null $email, string|int|float|bool|null $processId): self
    {
        $dto = new self();
        $dto->signature = self::normalizeString($signature);
        $dto->publicId = self::normalizeString($publicId);
        $dto->email = self::normalizeString($email);
        $dto->processId = self::normalizeString($processId);

        return $dto;
    }

    private static function normalizeString(string|int|float|bool|null $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
