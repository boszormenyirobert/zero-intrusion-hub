<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Service\Corporate\AuthorizedBackendResponseService;
use App\Service\Corporate\RequestIdentityService;
use App\Service\Corporate\SecureBackendClient;
use App\Service\Corporate\SecureRequestService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class SecureRequestServiceTest extends TestCase
{
    public function testGenerateRequestIdentityDelegatesToIdentityService(): void
    {
        $requestIdentityService = $this->createMock(RequestIdentityService::class);
        $requestIdentityService
            ->expects(self::once())
            ->method('generate')
            ->willReturn('request-id');

        $responseService = $this->createMock(AuthorizedBackendResponseService::class);
        $backendClient = $this->createMock(SecureBackendClient::class);

        $service = new SecureRequestService($requestIdentityService, $responseService, $backendClient);

        self::assertSame('request-id', $service->generateRequestIdentity());
    }

    public function testDecodeAuthorizedResponseDelegatesToResponseService(): void
    {
        $requestIdentityService = $this->createMock(RequestIdentityService::class);
        $responseService = $this->createMock(AuthorizedBackendResponseService::class);
        $responseService
            ->expects(self::once())
            ->method('decode')
            ->willReturn(['ok' => true]);

        $backendClient = $this->createMock(SecureBackendClient::class);
        $service = new SecureRequestService($requestIdentityService, $responseService, $backendClient);

        self::assertSame(['ok' => true], $service->decodeAuthorizedResponse(new JsonResponse()));
    }

    public function testPostSecureDelegatesToBackendClient(): void
    {
        $requestIdentityService = $this->createMock(RequestIdentityService::class);
        $responseService = $this->createMock(AuthorizedBackendResponseService::class);
        $backendClient = $this->createMock(SecureBackendClient::class);
        $jsonResponse = new JsonResponse(['status' => 'ok']);

        $backendClient
            ->expects(self::once())
            ->method('post')
            ->with(['process' => ['payload' => true]])
            ->willReturn($jsonResponse);

        $service = new SecureRequestService($requestIdentityService, $responseService, $backendClient);

        self::assertSame($jsonResponse, $service->postSecure(['process' => ['payload' => true]]));
    }

    public function testPostSecureAndDecodeDelegatesToClientAndResponseDecoder(): void
    {
        $requestIdentityService = $this->createMock(RequestIdentityService::class);
        $responseService = $this->createMock(AuthorizedBackendResponseService::class);
        $backendClient = $this->createMock(SecureBackendClient::class);
        $jsonResponse = new JsonResponse(['status' => 'ok']);

        $backendClient
            ->expects(self::once())
            ->method('post')
            ->with(['process' => ['payload' => true]])
            ->willReturn($jsonResponse);

        $responseService
            ->expects(self::once())
            ->method('decode')
            ->with($jsonResponse)
            ->willReturn(['authorized' => true]);

        $service = new SecureRequestService($requestIdentityService, $responseService, $backendClient);

        self::assertSame(['authorized' => true], $service->postSecureAndDecode(['process' => ['payload' => true]]));
    }
}
