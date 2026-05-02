<?php

namespace App\DTO;

class AccountViewDataDTO
{
    public function __construct(
        public bool $isJwtValid,
        public AuthenticatedUserDTO $user,
        public array $accounts,
        public ?SelectedSubscriptionDTO $businessSubscription,
        public bool $menuItemInstanceRegistration,
        public array $pills
    ) {
    }

    public function toArray(): array
    {
        return [
            'is_jwt_valid' => $this->isJwtValid,
            'user' => $this->user->toTemplateArray(),
            'accounts' => $this->accounts,
            'businessSubscription' => $this->businessSubscription?->toArray(),
            'menuItem_instanceRegistration' => $this->menuItemInstanceRegistration,
            'pills' => $this->pills,
        ];
    }
}
