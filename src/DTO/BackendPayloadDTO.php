<?php

namespace App\DTO;

class BackendPayloadDTO
{
    public function __construct(
        private array $data = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function keys(): array
    {
        return array_keys($this->data);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, string|int|float|bool|array|object|null $default = null): string|int|float|bool|array|object|null
    {
        return $this->data[$key] ?? $default;
    }
}
