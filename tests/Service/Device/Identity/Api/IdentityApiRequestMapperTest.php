<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Identity\Api;

use App\Service\Device\Identity\Api\IdentityApiRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class IdentityApiRequestMapperTest extends TestCase
{
    public function testMapRecoverySettingsPayloadReturnsDecodedObject(): void
    {
        $mapper = new IdentityApiRequestMapper($this->createMock(LoggerInterface::class));
        $request = Request::create('/api/secret/recovery-settings', 'POST', content: json_encode([
            'email' => 'user@example.test',
            'phone' => '+36123456789',
        ], JSON_THROW_ON_ERROR));

        $payload = $mapper->mapRecoverySettingsPayload($request);

        self::assertSame('user@example.test', $payload->email);
        self::assertSame('+36123456789', $payload->phone);
    }

    public function testMapRecoverySettingsPayloadRejectsInvalidJson(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $mapper = new IdentityApiRequestMapper($logger);
        $request = Request::create('/api/secret/recovery-settings', 'POST', content: '{invalid');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid JSON payload');
        $mapper->mapRecoverySettingsPayload($request);
    }

    public function testMapRecoverySettingsPayloadRejectsNonObjectPayload(): void
    {
        $mapper = new IdentityApiRequestMapper($this->createMock(LoggerInterface::class));
        $request = Request::create('/api/secret/recovery-settings', 'POST', content: '[]');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid recovery settings payload.');
        $mapper->mapRecoverySettingsPayload($request);
    }
}
