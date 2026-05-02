<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Registration\Api;

use App\Service\User\Registration\Api\RegistrationApiRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class RegistrationApiRequestMapperTest extends TestCase
{
    public function testMapRegistrationRequestMapsBodyAndInjectsHmac(): void
    {
        $mapper = new RegistrationApiRequestMapper($this->createMock(LoggerInterface::class));
        $request = Request::create('/api/user-registration', 'POST', content: json_encode([
            'publicId' => 'corp-1',
            'domain' => 'https://example.test',
        ], JSON_THROW_ON_ERROR));

        $dto = $mapper->mapRegistrationRequest($request, 'hmac-value');

        self::assertSame('corp-1', $dto->publicId);
        self::assertSame('https://example.test', $dto->domain);
        self::assertSame('hmac-value', $dto->hmac);
    }

    public function testMapCallbackRequestReturnsRegistrationProcessDto(): void
    {
        $mapper = new RegistrationApiRequestMapper($this->createMock(LoggerInterface::class));
        $request = Request::create('/api/registration/callback', 'POST', content: json_encode([
            'signature' => 'signature',
            'publicId' => 'public-id',
            'email' => 'user@example.test',
            'registrationProcessId' => 'process-123',
        ], JSON_THROW_ON_ERROR));

        $dto = $mapper->mapCallbackRequest($request);

        self::assertSame('public-id', $dto->getPublicId());
        self::assertSame('user@example.test', $dto->getEmail());
        self::assertSame('process-123', $dto->getProcessId());
    }

    public function testMapCallbackPayloadRejectsMissingFields(): void
    {
        $mapper = new RegistrationApiRequestMapper($this->createMock(LoggerInterface::class));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid registration callback payload.');

        $mapper->mapCallbackPayload(['signature' => 'only-signature']);
    }

    public function testDecodeCallbackPayloadReturnsEmptyArrayForScalarJson(): void
    {
        $mapper = new RegistrationApiRequestMapper($this->createMock(LoggerInterface::class));
        $request = Request::create('/api/registration/callback', 'POST', content: '"scalar"');

        self::assertSame([], $mapper->decodeCallbackPayload($request));
    }

    public function testDecodeCallbackPayloadRejectsInvalidJson(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Invalid registration API JSON payload', self::callback(static fn (array $context): bool => array_key_exists('error', $context)));

        $mapper = new RegistrationApiRequestMapper($logger);
        $request = Request::create('/api/registration/callback', 'POST', content: '{invalid');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid JSON payload');

        $mapper->decodeCallbackPayload($request);
    }
}
