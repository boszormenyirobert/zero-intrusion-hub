<?php

namespace App\DTO;

class MenuAvailabilityDTO
{
    public bool $availabilitySettings = false;
    public bool $availabilityInstance = false;
    public bool $availabilityUsers = false;

    public function __construct(
        bool $availabilitySettings = false,
        bool $availabilityInstance = false,
        bool $availabilityUsers = false
    ) {
        $this->availabilitySettings = $availabilitySettings;
        $this->availabilityInstance = $availabilityInstance;
        $this->availabilityUsers = $availabilityUsers;
    }

    public function toArray(): array
    {
        return [
            'availability_settings' => $this->availabilitySettings,
            'availability_instance' => $this->availabilityInstance,
            'availability_users' => $this->availabilityUsers,
        ];
    }
}
