<?php

namespace App\DTO;

class RestorePinRequestDTO
{
    public function __construct(
        public ReplaceDevicePinDTO $data,
        public string $replaceHash
    ) {
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data->toArray(),
            'replaceHash' => $this->replaceHash,
        ];
    }
}
