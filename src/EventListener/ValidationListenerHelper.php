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
     * @param bool $required - if true, domain must be present and non-empty; if false, domain can be empty but must be present
     * @param array &$errors
     */
    public static function validateDomain(array $data, bool $required, array &$errors): void
    {
        if (!isset($data['domain']) || !is_string($data['domain'])) {
            return;
        }

        if(!$required && trim($data['domain']) === '') {
            return;
        }

        $domain = trim(strtolower($data['domain']));

        // Allow port number after colon, but validate only the domain part
        $parts = explode(':', $domain);
        $host = $parts[0];
        $port = $parts[1] ?? null;

        // convert IDN → ASCII
        $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        if ($ascii === false) {
            $errors['domain'] = 'Invalid domain.';
            return;
        }

        if (strlen($ascii) > 253) {
            $errors['domain'] = 'Domain too long.';
            return;
        }

        if (!preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))*$/', $ascii)) {
            $errors['domain'] = 'Invalid domain format.';
            return;
        }

        // Port ellenőrzés, ha meg van adva
        if ($port !== null) {
            if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
                $errors['domain'] = 'Invalid port number.';
            }
        }
    }
    
    /**
     * Validates the userPublicId field (Base64: A-Za-z0-9+/=)
     * @param array $data
     * @param array &$errors
     */
    public static function validateUserPublicId(array $data, array &$errors): void
    {
        $field = 'userPublicId';
        // userPublicId can be empty but must be present, so we only check for presence, not emptiness
        if ($field === 'userPublicId'  && trim($data['userPublicId']) == '') {
            return;            
        } else       

        if (isset($data['userPublicId']) && is_string($data['userPublicId']) && trim($data['userPublicId']) !== '') {
            if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $data['userPublicId'])) {
                $errors['userPublicId'] = 'userPublicId contains invalid characters (allowed: A-Z, a-z, 0-9, +, /, =).';
            }
        }
    }  
    /**
     * Validates the userPublicId field (Base64: A-Za-z0-9+/=)
     * @param array $data
     * @param array &$errors
     */
    public static function validateTargetId(array $data, array &$errors): void
    {
        if (isset($data['targetId']) && is_string($data['targetId']) && trim($data['targetId']) !== '') {           
            if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $data['targetId'])) {
                $errors['targetId'] = 'targetId contains invalid characters (allowed: A-Z, a-z, 0-9, +, /, =).';
            }
        }
    }         

    public static function validateApplication(array $data, array &$errors): void
    {
        if (!isset($data['application']) || !is_string($data['application'])) {
            return;
        }

        $desc = trim($data['application']);

        if ($desc === '') {
            return;
        }

        if (mb_strlen($desc) > 100) {
            $errors['application'] = 'application too long';
            return;
        }

        // binary control chars 
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $desc)) {
            $errors['application'] = 'invalid control characters';
            return;
        }

        if ($desc !== strip_tags($desc)) {
            $errors['application'] = 'html not allowed';
            return;
        }
    }

    public static function validateDescription(array $data, array &$errors): void
    {
        if (!isset($data['description']) || !is_string($data['description'])) {
            return;
        }

        $desc = trim($data['description']);

        if ($desc === '') {
            return;
        }

        if (mb_strlen($desc) > 2000) {
            $errors['description'] = 'description too long';
            return;
        }

        // binary control chars 
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $desc)) {
            $errors['description'] = 'invalid control characters';
            return;
        }

        if ($desc !== strip_tags($desc)) {
            $errors['description'] = 'html not allowed';
            return;
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

            // userPublicId can be empty but must be present, so we only check for presence, not emptiness
            if ($field === 'userPublicId') {
                if (!array_key_exists($field, $data)) {
                    $errors[$field] = ucfirst($field) . ' is required.';
                }
                continue;
            }

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

    public static function validateEmail(array $data, array &$errors): void
    {
        if (!isset($data['email']) || !is_string($data['email']) || trim($data['email']) === '') {
            $errors['email'] = 'Email is required and must be a non-empty string.';
            return;
        }
        $email = trim($data['email']);

        if (preg_match('/[\x00-\x1F\x7F]/', $email)) {
            $errors['email_chars'] = 'Invalid characters in email.';
            return;
        }  
        if (mb_strlen($email) > 254) {
            $errors['email_length'] = 'Email too long.';
            return;
        }  
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email_format'] = 'Invalid email format.';
            return;
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