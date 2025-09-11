<?php

namespace App\Service\Crypters;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * Data flow between service and public
 */
final class CrypterService
{
    private const CIPHER = 'aes-256-cbc';
    private string $key;
    private string $iv;
    private array|string $data;

    public function __construct(array|string $data, ContainerBagInterface $params)
    {
        $this->data = $data;
        $this->key = $params->get('DATA_HASH_SECRET');
        $this->setIv();
    }

    /**
     * Encrypt data using AES-256-CBC
     */
    public function encryptData(): string
    {
        $plaintext = json_encode($this->data);
        $encrypted = openssl_encrypt($plaintext, self::CIPHER, $this->key, 0, $this->iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($this->iv . $encrypted);
    }

    /**
     * Decrypt data from encrypted base64 string
     * @param bool $isDTO Whether the decrypted data should be returned as a DTO
     * @return array|string Decrypted data
     */
    public function decryptData(bool $isDTO = false): array|string
    {
        $data = base64_decode($this->data);
        $this->setIv(false, $data);

        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $this->key, 0, $this->iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        if ($isDTO) {
            $decrypted = $this->decodeJson($decrypted);
        }

        return $decrypted;
    }

    /**
     * Set the initialization vector (IV) for encryption/decryption
     * @param bool $encrypt Whether the IV should be generated for encryption or not
     * @param string|null $data The data to extract IV from (for decryption)
     */
    private function setIv(bool $encrypt = true, string $data = ""): void
    {
        $this->iv = $encrypt ? openssl_random_pseudo_bytes(16) : substr($data, 0, 16);
    }

    /**
     * Decode JSON data
     * @param string $data JSON string to decode
     * @return array
     */
    private function decodeJson(string $data): array
    {
        $decoded = json_decode($data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON decoding failed: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
