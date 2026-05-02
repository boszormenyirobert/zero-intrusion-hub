<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Login\Api;

use App\DTO\CorporateIdentificationDTO;
use App\DTO\QrCodeResponseDTO;
use App\DTO\RegistrationProcessDTO;
use App\Service\User\Login\Api\LoginApiRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class LoginApiRequestMapperTest extends TestCase
{
    public function testMapLoginRequestReturnsCorporateIdentificationDto(): void
    {
        $mapper = new LoginApiRequestMapper($this->createMock(LoggerInterface::class));
        $request = Request::create('/api/user-login', 'POST', content: json_encode([
            'publicId' => 'cid_public',
            'domain' => 'example.test',
            'hmac' => 'hmac-value',
            'userPublicId' => 'user-public-id',
        ], JSON_THROW_ON_ERROR));

        $dto = $mapper->mapLoginRequest($request);

        self::assertInstanceOf(CorporateIdentificationDTO::class, $dto);
        self::assertSame('cid_public', $dto->publicId);
        self::assertSame('example.test', $dto->domain);
        self::assertSame('hmac-value', $dto->hmac);
        self::assertSame('user-public-id', $dto->userPublicId);
    }

    public function testMapCallbackRequestRejectsMissingFields(): void
    {
        $mapper = new LoginApiRequestMapper($this->createMock(LoggerInterface::class));
        $request = Request::create('/api/user-login/callback', 'POST', content: json_encode([
            'signature' => 'signature',
            'publicId' => 'public-id',
        ], JSON_THROW_ON_ERROR));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid login callback payload.');
        $mapper->mapCallbackRequest($request);
    }

    public function testMapCallbackRequestReturnsRegistrationProcessDto(): void
    {
        $mapper = new LoginApiRequestMapper($this->createMock(LoggerInterface::class));
        $request = Request::create('/api/user-login/callback', 'POST', content: json_encode([
            'signature' => 'signature',
            'publicId' => 'public-id',
            'email' => 'user@example.test',
            'processId' => 'process-123',
        ], JSON_THROW_ON_ERROR));

        $dto = $mapper->mapCallbackRequest($request);

        self::assertInstanceOf(RegistrationProcessDTO::class, $dto);
        self::assertSame('process-123', $dto->getProcessId());
    }

    public function testMapCheckRequestRejectsMissingDomainProcessId(): void
    {
        $mapper = new LoginApiRequestMapper($this->createMock(LoggerInterface::class));
        $request = Request::create('/api/user-login/check', 'POST', content: '{}');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid login check payload.');
        $mapper->mapCheckRequest($request);
    }

    public function testMapCheckRequestReturnsQrCodeResponseDto(): void
    {
        $mapper = new LoginApiRequestMapper($this->createMock(LoggerInterface::class));
        $request = Request::create('/api/user-login/check', 'POST', content: json_encode([
            'domainProcessId' => 'process-123',
            'qrCode' => 'qr-content',
        ], JSON_THROW_ON_ERROR));

        $dto = $mapper->mapCheckRequest($request);

        self::assertInstanceOf(QrCodeResponseDTO::class, $dto);
        self::assertSame('process-123', $dto->getDomainProcessId());
    }

    public function testMapRequestsRejectInvalidJsonAndLogError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $mapper = new LoginApiRequestMapper($logger);
        $request = Request::create('/api/user-login', 'POST', content: '{invalid');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid JSON payload');
        $mapper->mapLoginRequest($request);
    }
}
