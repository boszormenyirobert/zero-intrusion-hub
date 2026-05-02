<?php

declare(strict_types=1);

namespace App\Tests\Logger;

use App\Logger\LogTrace;
use PHPUnit\Framework\TestCase;

final class LogTraceTest extends TestCase
{
    public function testFingerprintReturnsNullForEmptyInputAndHashForContent(): void
    {
        self::assertNull(LogTrace::fingerprint(null));
        self::assertNull(LogTrace::fingerprint(''));
        self::assertSame(substr(hash('sha256', 'secret'), 0, 12), LogTrace::fingerprint('secret'));
    }

    public function testSummarizeStringContentHandlesNullJsonAndPlainText(): void
    {
        self::assertSame(['content_present' => false], LogTrace::summarizeStringContent(null));

        $jsonSummary = LogTrace::summarizeStringContent('{"alpha":1,"beta":2}');
        self::assertTrue($jsonSummary['content_present']);
        self::assertSame(['alpha', 'beta'], $jsonSummary['content_json_keys']);

        $plainSummary = LogTrace::summarizeStringContent('plain-text');
        self::assertTrue($plainSummary['content_present']);
        self::assertArrayNotHasKey('content_json_keys', $plainSummary);
    }
}
