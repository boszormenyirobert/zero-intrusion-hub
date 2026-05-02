<?php

namespace App\Logger;

final class SensitiveDataProcessor
{
    public function __construct(
        private SensitiveDataSanitizer $sanitizer
    ) {
    }

    public function __invoke(array $record): array
    {
        $record['context'] = $this->sanitizer->sanitizeArray($record['context'] ?? []);
        $record['extra'] = $this->sanitizer->sanitizeArray($record['extra'] ?? []);

        return $record;
    }
}
