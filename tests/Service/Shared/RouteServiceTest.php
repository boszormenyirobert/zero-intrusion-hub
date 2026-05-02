<?php

declare(strict_types=1);

namespace App\Tests\Service\Shared;

use App\DTO\BackendRouteDTO;
use App\Service\Shared\ProcessRouteRegistry;
use App\Service\Shared\RouteService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RouteServiceTest extends TestCase
{
    public function testMapRouteBuildsAbsoluteUrlForKnownProcessKey(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $registry = $this->createMock(ProcessRouteRegistry::class);
        $registry
            ->expects(self::once())
            ->method('resolve')
            ->with('known_process')
            ->willReturn(new BackendRouteDTO('/base', '/endpoint'));

        $service = new RouteService($logger, $registry, 'https://example.test');

        $result = $service->mapRoute(['known_process' => ['payload' => true]]);

        self::assertSame('https://example.test/base/endpoint', $result);
    }

    public function testMapRouteReturnsEmptyStringWhenProcessKeyMissing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Unable to resolve backend route because no process key was provided.',
                ['payload_keys' => []],
            );

        $registry = $this->createMock(ProcessRouteRegistry::class);
        $registry->expects(self::never())->method('resolve');

        $service = new RouteService($logger, $registry, 'https://example.test');

        self::assertSame('', $service->mapRoute([]));
    }

    public function testMapRouteReturnsEmptyStringWhenProcessKeyUnknown(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Unable to resolve backend route because process key is unknown.',
                ['process_key' => 'unknown_process'],
            );

        $registry = $this->createMock(ProcessRouteRegistry::class);
        $registry
            ->expects(self::once())
            ->method('resolve')
            ->with('unknown_process')
            ->willReturn(null);

        $service = new RouteService($logger, $registry, 'https://example.test');

        self::assertSame('', $service->mapRoute(['unknown_process' => ['payload' => true]]));
    }
}
