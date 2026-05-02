<?php

declare(strict_types=1);

namespace App\Tests\Service\Instance\HUB;

use App\Logger\LogTrace;
use App\Service\Instance\HUB\JwtContextService;
use App\Service\JWT\JwtService;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class JwtContextServiceTest extends TestCase
{
    public function testBuildReturnsInvalidContextWithoutToken(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $jwtService
            ->expects(self::once())
            ->method('extractTokenFromRequest')
            ->willReturn(null);
        $jwtService
            ->expects(self::never())
            ->method('extractPayloadFromRequest');

        $service = new JwtContextService($jwtService, $this->createMock(LoggerInterface::class));
        $context = $service->build(Request::create('/'));

        self::assertFalse($context->isJwtValid);
        self::assertSame('', $context->userPublicId);
        self::assertSame('', $context->userEmail);
        self::assertNull($context->payload);
    }

    public function testBuildReturnsValidContextForValidPayload(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', 'account');

        $jwtService = $this->createMock(JwtService::class);
        $jwtService
            ->expects(self::once())
            ->method('extractTokenFromRequest')
            ->with($request)
            ->willReturn('jwt-token');
        $jwtService
            ->expects(self::once())
            ->method('extractPayloadFromRequest')
            ->with($request)
            ->willReturn([
                'publicId' => 'public-id',
                'username' => 'user@example.test',
            ]);

        $logger = new class () extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $service = new JwtContextService($jwtService, $logger);
        $context = $service->build($request);

        self::assertTrue($context->isJwtValid);
        self::assertSame('public-id', $context->userPublicId);
        self::assertSame('user@example.test', $context->userEmail);
        self::assertSame('user@example.test', $context->payload['username']);
        self::assertCount(1, $logger->records);
        self::assertSame('debug', $logger->records[0]['level']);
        self::assertSame('JWT context built with valid JWT', $logger->records[0]['message']);
        self::assertSame([
            'route' => 'account',
            'user_public_id_hash' => LogTrace::fingerprint('public-id'),
            'user_email_hash' => LogTrace::fingerprint('user@example.test'),
        ], $logger->records[0]['context']);
        self::assertArrayNotHasKey('user_public_id', $logger->records[0]['context']);
        self::assertArrayNotHasKey('user_email', $logger->records[0]['context']);
    }

    public function testBuildReturnsInvalidContextForInvalidPayload(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', 'account');

        $jwtService = $this->createMock(JwtService::class);
        $jwtService
            ->expects(self::once())
            ->method('extractTokenFromRequest')
            ->with($request)
            ->willReturn('jwt-token');
        $jwtService
            ->expects(self::once())
            ->method('extractPayloadFromRequest')
            ->with($request)
            ->willReturn(null);

        $logger = new class () extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $service = new JwtContextService($jwtService, $logger);
        $context = $service->build($request);

        self::assertFalse($context->isJwtValid);
        self::assertSame('', $context->userPublicId);
        self::assertSame('', $context->userEmail);
        self::assertNull($context->payload);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('JWT context built with invalid JWT', $logger->records[0]['message']);
    }
}
