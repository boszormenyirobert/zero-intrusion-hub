<?php

namespace App\DTO;

class RegistrationViewDataDTO
{
    public function __construct(
        public QrCodeResponseDTO $qrCode,
        public bool $menuItemInstanceRegistration,
        public string $action,
        public MenuAvailabilityDTO $availabilities
    ) {
    }

    public function toArray(): array
    {
        return [
            'qrCode' => $this->qrCode->toArray(),
            'menuItem_instanceRegistration' => $this->menuItemInstanceRegistration,
            'processId' => $this->qrCode->getRegistrationProcessId(),
            'action' => $this->action,
            'availabilities' => $this->availabilities->toArray(),
        ];
    }
}
