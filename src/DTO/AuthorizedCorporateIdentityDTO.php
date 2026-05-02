<?php

namespace App\DTO;

class AuthorizedCorporateIdentityDTO
{
    public string $corporateId = '';
    public string $corporateIdKey = '';
    public string $corporateIdSecret = '';
    public string $sslPublicKey = '';

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->corporateId = (string) ($data['corporate_id'] ?? '');
        $dto->corporateIdKey = (string) ($data['corporate_id_key'] ?? '');
        $dto->corporateIdSecret = (string) ($data['corporate_id_secret'] ?? '');
        $dto->sslPublicKey = (string) ($data['ssl_public_key'] ?? '');

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'corporate_id' => $this->corporateId,
            'corporate_id_key' => $this->corporateIdKey,
            'corporate_id_secret' => $this->corporateIdSecret,
            'ssl_public_key' => $this->sslPublicKey,
        ];
    }
}
