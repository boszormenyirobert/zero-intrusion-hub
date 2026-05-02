<?php

declare(strict_types=1);

namespace App\Tests\Logger;

use App\Logger\SensitiveDataSanitizer;
use PHPUnit\Framework\TestCase;

final class SensitiveDataSanitizerTest extends TestCase
{
    public function testSanitizeArrayRedactsSensitiveScalarsAndPayloads(): void
    {
        $sanitizer = new SensitiveDataSanitizer();

        $result = $sanitizer->sanitizeArray([
            'email' => 'user@example.test',
            'payload' => ['secret' => 'value', 'plain' => 'text'],
            'body' => json_encode(['a' => 1, 'b' => 2], JSON_THROW_ON_ERROR),
            'note' => 'safe',
        ]);

        self::assertStringContainsString('[redacted:email hash=', $result['email']);
        self::assertStringContainsString('[redacted:payload array hash=', $result['payload']);
        self::assertStringContainsString('keys=secret,plain', $result['payload']);
        self::assertStringContainsString('[redacted:body json hash=', $result['body']);
        self::assertSame('safe', $result['note']);
    }

    public function testSanitizeValueSupportsObjectsPlainPayloadsAndEmptyStructuredPayloads(): void
    {
        $sanitizer = new SensitiveDataSanitizer();

        $objectResult = $sanitizer->sanitizeValue((object) ['token' => 'abc', 'plain' => 'ok']);
        self::assertIsArray($objectResult);
        self::assertStringContainsString('[redacted:token hash=', $objectResult['token']);
        self::assertSame('ok', $objectResult['plain']);

        $plainPayload = $sanitizer->sanitizeValue('not-json', 'request_body');
        self::assertStringContainsString('[redacted:request_body hash=', $plainPayload);

        $emptyPayload = $sanitizer->sanitizeValue('   ', 'content');
        self::assertSame('[redacted:content empty]', $emptyPayload);
    }
}
