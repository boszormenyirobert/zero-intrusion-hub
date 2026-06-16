<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\HttpClientFactory;
use PHPUnit\Framework\TestCase;

final class HttpClientFactoryTest extends TestCase
{
    public function testBuildOptionsOmitsEmptyTlsFileSettings(): void
    {
        self::assertSame([
            'verify_peer' => true,
            'verify_host' => true,
        ], HttpClientFactory::buildOptions(true, true, '', ' ', '', "\t", ''));
    }

    public function testBuildOptionsIncludesProvidedTlsSettings(): void
    {
        self::assertSame([
            'verify_peer' => true,
            'verify_host' => true,
            'cafile' => '/certs/ca.pem',
            'capath' => '/certs',
            'local_cert' => '/certs/client.pem',
            'local_pk' => '/certs/client.key',
            'passphrase' => 'secret',
        ], HttpClientFactory::buildOptions(
            true,
            true,
            ' /certs/ca.pem ',
            '/certs',
            '/certs/client.pem',
            '/certs/client.key',
            'secret'
        ));
    }
}