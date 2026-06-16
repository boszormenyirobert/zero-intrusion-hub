<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\CurlHttpClient;

final class HttpClientFactory
{
public static function create(
    bool $verifyPeer,
    bool $verifyHost,
    string $caFile,
    string $caPath,
    string $localCert,
    string $localPk,
    string $passphrase
): HttpClientInterface {
    return new \Symfony\Component\HttpClient\CurlHttpClient(
        self::buildOptions(
            $verifyPeer,
            $verifyHost,
            $caFile,
            $caPath,
            $localCert,
            $localPk,
            $passphrase
        )
    );
}

    /**
     * @return array<string, bool|string>
     */
    public static function buildOptions(
        bool $verifyPeer,
        bool $verifyHost,
        string $caFile,
        string $caPath,
        string $localCert,
        string $localPk,
        string $passphrase
    ): array {
        $options = [
            'verify_peer' => $verifyPeer,
            'verify_host' => $verifyHost,
        ];

        $map = [
            'cafile' => $caFile,
            'capath' => $caPath,
            'local_cert' => $localCert,
            'local_pk' => $localPk,
            'passphrase' => $passphrase,
        ];

        foreach ($map as $key => $value) {
            $value = trim($value);

            if ($value !== '') {
                $options[$key] = $value;
            }
        }

        return $options;
    }
}