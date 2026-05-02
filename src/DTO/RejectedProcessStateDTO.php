<?php

namespace App\DTO;

class RejectedProcessStateDTO
{
    public function __construct(
        public string $status = 'rejected',
        public ?string $reason = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'reason' => $this->reason,
        ];
    }
}
