<?php

namespace App\EventListener;

class ValidationListenerHelper
{

    /**
     * Validates the source field 
     * @param array $data
     * @param array &$errors
     */
    public static function validateSource(string $source, string $type, array &$errors): void
    {
        $source = trim($source);

        if ($source !== $type) {
            $errors['source'] = sprintf('Invalid source value.');
        }
    }

    /**
     * Validates the domain field according to RFC 1035/1123 (A-Z, a-z, 0-9, hyphen, dot; max 253 chars, each label max 63 chars, labels cannot start/end with hyphen)
     * @param array $data
     * @param array &$errors
     */
    public static function validateDomain(array $data, array &$errors): void
    {
        if (!isset($data['domain']) || !is_string($data['domain'])) {
            return;
        }

        $domain = trim(strtolower($data['domain']));

        // convert IDN → ASCII
        $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        if ($ascii === false) {
            $errors['domain'] = 'Invalid domain.';
            return;
        }

        if (strlen($ascii) > 253) {
            $errors['domain'] = 'Domain too long.';
            return;
        }

        if (!preg_match('/^(?=.{1,253}$)(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/', $ascii)) {
            $errors['domain'] = 'Invalid domain format.';
        }
    }
    
    /**
     * Validates the userPublicId field (Base64: A-Za-z0-9+/=)
     * @param array $data
     * @param array &$errors
     */
    public static function validateUserPublicId(array $data, array &$errors): void
    {
        if (isset($data['userPublicId']) && is_string($data['userPublicId']) && trim($data['userPublicId']) !== '') {
            if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $data['userPublicId'])) {
                $errors['userPublicId'] = 'userPublicId contains invalid characters (allowed: A-Z, a-z, 0-9, +, /, =).';
            }
        }
    }    

    /**
     * Validates required fields for presence and non-empty string value.
     * @param array $data
     * @param array $requiredFields
     * @param array &$errors
     */
    public static function validateRequiredFields(array $data, array $requiredFields, array &$errors): void
    {
        foreach ($requiredFields as $field) {
            $value = $data[$field] ?? null; 
            if (empty($value) || !is_string($value) || trim($value) === '') {
                $errors[$field] = ucfirst($field) . ' is required and must be a non-empty string.';
            }
        }
    }    
    
    /**
     * Validates the iv field: must be a valid base64 string and decode to exactly 16 bytes.
     * @param array $data
     * @param array &$errors
     */
    public static function validateIv(array $data, array &$errors): void
    {
        if (!isset($data['iv']) || !is_string($data['iv'])) {
            $errors['iv'] = 'iv is required.';
            return;
        }

        $decoded = base64_decode($data['iv'], true);

        if ($decoded === false || strlen($decoded) !== 16) {
            $errors['iv'] = 'Invalid IV.';
        }
    }
    
    /**
     * Validates the processId field: must be present, 22 chars, and base64-decode to 16 bytes.
     * @param array $data
     * @param array &$errors
     */
    public static function validateProcessId(array $data, array &$errors): void
    {
        if (!isset($data['processId']) || !is_string($data['processId'])) {
            $errors['processId'] = 'processId is required.';
            return;
        }

        $id = $data['processId'];

        if (strlen($id) !== 22) {
            $errors['processId'] = 'Invalid processId.';
            return;
        }

        $decoded = base64_decode($id, true);

        if ($decoded === false || strlen($decoded) !== 16) {
            $errors['processId'] = 'Invalid processId.';
        }
    }

    public static function validateNoControlChars(array $data, array $fields, array &$errors): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                continue;
            }

            if (preg_match('/[\x00-\x1F\x7F]/u', $data[$field])) {
                $errors[$field] = 'Contains invalid control characters';
            }
        }
    }    

}