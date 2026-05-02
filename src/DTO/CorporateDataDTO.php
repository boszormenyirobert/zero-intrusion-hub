<?php

namespace App\DTO;

class CorporateDataDTO
{
    public string $callbackUserLogin = '';
    public string $callbackUserRegistration = '';
    public string $corporateId = '';
    public string $domain = '';

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->callbackUserLogin = (string) ($data['callbackUserLogin'] ?? '');
        $dto->callbackUserRegistration = (string) ($data['callbackUserRegistration'] ?? '');
        $dto->corporateId = (string) ($data['corporateId'] ?? '');
        $dto->domain = (string) ($data['domain'] ?? '');

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'callbackUserLogin' => $this->callbackUserLogin,
            'callbackUserRegistration' => $this->callbackUserRegistration,
            'corporateId' => $this->corporateId,
        ];
    }
}
