<?php

namespace App\DTO;

class PollRequestDTO
{
    public function __construct(
        public string $processId,
        public string $action
    ) {
    }
}
