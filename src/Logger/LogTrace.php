<?php

namespace App\Logger;

final class LogTrace
{
    public static function fingerprint(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr(hash('sha256', $value), 0, 12);
    }

    public static function summarizeStringContent(?string $content): array
    {
        if ($content === null) {
            return [
                'content_present' => false,
            ];
        }

        $summary = [
            'content_present' => true,
            'content_length' => strlen($content),
            'content_hash' => self::fingerprint($content),
        ];

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                $summary['content_json_keys'] = array_slice(array_map('strval', array_keys($decoded)), 0, 10);
            }
        } catch (\JsonException) {
        }

        return $summary;
    }
}
