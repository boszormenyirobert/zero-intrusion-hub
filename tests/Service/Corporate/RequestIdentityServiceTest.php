<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Service\Corporate\RequestIdentityService;
use App\Service\Instance\InstanceSettingsService;
use PHPUnit\Framework\TestCase;

final class RequestIdentityServiceTest extends TestCase
{
    public function testGenerateBuildsExpectedHmacForCurrentTimestamp(): void
    {
        $settingsService = $this->createMock(InstanceSettingsService::class);
        $settingsService->expects(self::once())->method('getSecret')->willReturn('service-secret');
        $settingsService->expects(self::once())->method('getCorporateKey')->willReturn('service-key');

        $service = new RequestIdentityService($settingsService);

        $before = time();
        $result = $service->generate();
        $after = time();

        $expectedHashes = [];
        for ($timestamp = $before; $timestamp <= $after; ++$timestamp) {
            $expectedHashes[] = hash_hmac('sha256', 'service-key|' . $timestamp, 'service-secret');
        }

        self::assertContains($result, $expectedHashes);
    }
}
