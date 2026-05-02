<?php

namespace App\DTO;

class SelectedSubscriptionDTO
{
    public function __construct(
        public int|string|null $id = null,
        public string $subscription = ''
    ) {
    }

    public function toArray(): array
    {
        return [
            'subscription' => $this->subscription,
            'id' => $this->id,
        ];
    }
}
