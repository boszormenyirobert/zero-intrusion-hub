<?php

namespace App\DTO;

class LoginViewDataDTO
{
    public function __construct(
        public string $processId,
        public QrCodeResponseDTO $qrCodeData,
        public string $qrCode,
        public array $user,
        public string $action,
        public MenuAvailabilityDTO $availabilities
    ) {
    }

    public function toArray(): array
    {
        return [
            'processId' => $this->processId,
            'qrCodeData' => $this->qrCodeData->toArray(),
            'qrCode' => $this->qrCode,
            'user' => $this->user,
            'action' => $this->action,
            'availabilities' => $this->availabilities->toArray(),
        ];
    }
}
