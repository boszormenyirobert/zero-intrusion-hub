<?php

declare(strict_types=1);

namespace App\Tests\Logger;

use App\Logger\RequestIdProcessor;
use App\Logger\SensitiveDataProcessor;
use App\Logger\SensitiveDataSanitizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ProcessorCoverageTest extends TestCase
{
    public function testRequestIdProcessorLeavesRecordUntouchedWithoutCurrentRequest(): void
    {
        $processor = new RequestIdProcessor(new RequestStack());
        $record = ['extra' => []];

        self::assertSame($record, $processor($record));
    }

    public function testRequestIdProcessorUsesExistingHeaderValue(): void
    {
        $stack = new RequestStack();
        $request = Request::create('/');
        $request->headers->set('X-Request-Id', 'request-id-123');
        $stack->push($request);

        $processor = new RequestIdProcessor($stack);
        $record = $processor(['extra' => []]);

        self::assertSame('request-id-123', $record['extra']['request_id']);
    }

    public function testRequestIdProcessorGeneratesIdentifierWhenHeaderMissing(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        $processor = new RequestIdProcessor($stack);
        $record = $processor(['extra' => []]);

        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $record['extra']['request_id']);
    }

    public function testSensitiveDataProcessorSanitizesContextAndExtra(): void
    {
        $processor = new SensitiveDataProcessor(new SensitiveDataSanitizer());
        $record = $processor([
            'context' => ['secret' => 'value'],
            'extra' => ['token' => 'value'],
        ]);

        self::assertStringContainsString('[redacted:secret', $record['context']['secret']);
        self::assertStringContainsString('[redacted:token', $record['extra']['token']);
    }
}
