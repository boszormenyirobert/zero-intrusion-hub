<?php

namespace App\Logger;

final class SensitiveDataSanitizer
{
    private const HASH_LENGTH = 12;

    private const STRUCTURED_PAYLOAD_KEYS = [
        'content',
        'raw_content',
        'response',
        'response_body',
        'payload',
        'request_body',
        'body',
    ];

    public function sanitizeArray(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue($value, is_string($key) ? $key : null);
        }

        return $sanitized;
    }

    public function sanitizeValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            if ($key !== null && $this->isPayloadKey($key)) {
                return $this->summarizeArrayPayload($key, $value);
            }

            return $this->sanitizeArray($value);
        }

        if (is_object($value)) {
            return $this->sanitizeValue(get_object_vars($value), $key);
        }

        if (!is_string($value)) {
            return $value;
        }

        if ($key !== null && $this->isPayloadKey($key)) {
            return $this->summarizeStringPayload($key, $value);
        }

        if ($key !== null && $this->isSensitiveKey($key)) {
            return $this->redactScalar($key, $value);
        }

        return $value;
    }

    private function isPayloadKey(string $key): bool
    {
        return in_array($this->normalizeKey($key), self::STRUCTURED_PAYLOAD_KEYS, true);
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = $this->normalizeKey($key);

        return preg_match('/(^|_)(email|public_id|publicid|private_id|privateid|process_id|processid|domain_process_id|registration_process_id|userpublicid|corporateid|corporate_id|corporatekey|corporate_key|secret|signature|token|cookie|auth|hmac|iv|ssl_public_key|responsebody)(_|$)/', $normalizedKey) === 1;
    }

    private function redactScalar(string $key, string $value): string
    {
        $normalizedKey = $this->normalizeKey($key);

        if (str_contains($normalizedKey, 'email')) {
            return sprintf('[redacted:email hash=%s]', $this->fingerprint($value));
        }

        return sprintf('[redacted:%s hash=%s len=%d]', $normalizedKey, $this->fingerprint($value), strlen($value));
    }

    private function summarizeStringPayload(string $key, string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return sprintf('[redacted:%s empty]', $this->normalizeKey($key));
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                return sprintf(
                    '[redacted:%s json hash=%s len=%d keys=%s]',
                    $this->normalizeKey($key),
                    $this->fingerprint($value),
                    strlen($value),
                    implode(',', array_map('strval', array_slice(array_keys($decoded), 0, 10)))
                );
            }
        } catch (\JsonException) {
        }

        return sprintf(
            '[redacted:%s hash=%s len=%d]',
            $this->normalizeKey($key),
            $this->fingerprint($value),
            strlen($value)
        );
    }

    private function summarizeArrayPayload(string $key, array $value): string
    {
        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $encoded = serialize($value);
        }

        return sprintf(
            '[redacted:%s array hash=%s keys=%s]',
            $this->normalizeKey($key),
            $this->fingerprint($encoded),
            implode(',', array_map('strval', array_slice(array_keys($value), 0, 10)))
        );
    }

    private function fingerprint(string $value): string
    {
        return substr(hash('sha256', $value), 0, self::HASH_LENGTH);
    }

    private function normalizeKey(string $key): string
    {
        $normalized = strtolower(trim($key));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return $normalized;
    }
}
