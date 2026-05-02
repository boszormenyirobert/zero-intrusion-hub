<?php

declare(strict_types=1);

namespace App\Tests\Service\Qr;

use App\Service\Qr\GenerateQrService;
use PHPUnit\Framework\TestCase;

final class GenerateQrServiceTest extends TestCase
{
    public function testGetQrCodeReturnsBase64EncodedPng(): void
    {
        $service = new GenerateQrService();

        $encoded = $service->getQrCode([
            'domain' => 'example.test',
            'processId' => 'process-123',
        ]);

        $decoded = base64_decode($encoded, true);

        self::assertNotFalse($decoded);
        self::assertStringStartsWith("\x89PNG", $decoded);
    }
}
