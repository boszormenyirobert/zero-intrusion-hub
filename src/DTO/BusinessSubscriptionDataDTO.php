<?php

namespace App\DTO;

class BusinessSubscriptionDataDTO
{
    public array $accounts = [];
    public array $businessSubscription = [];

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->accounts = is_array($data['accounts'] ?? null) ? $data['accounts'] : [];
        $dto->businessSubscription = is_array($data['businessSubscription'] ?? null) ? $data['businessSubscription'] : [];

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'accounts' => $this->accounts,
            'businessSubscription' => $this->businessSubscription,
        ];
    }
}
