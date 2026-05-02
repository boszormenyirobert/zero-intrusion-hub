<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\User\Login\Api;

use App\DTO\QrCodeResponseDTO;
use App\Service\Instance\HUB\InstanceRegistrationService;
use App\Service\User\Callback\UserProcessCallbackService;
use App\Service\User\UserQrCodeService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerIntegrationTest extends WebTestCase
{
    public function testApiLoginReturnsQrPayloadThroughHttpKernel(): void
    {
        $client = static::createClient();

        $instanceRegistrationService = $this->createMock(InstanceRegistrationService::class);
        $instanceRegistrationService
            ->method('getInitializationState')
            ->willReturn(false);

        static::getContainer()->set(InstanceRegistrationService::class, $instanceRegistrationService);

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::once())
            ->method('getQrCode')
            ->willReturn(QrCodeResponseDTO::fromArray([
                'domainProcessId' => 'process-123',
                'qrCode' => 'qr-content',
            ]));

        static::getContainer()->set(UserQrCodeService::class, $qrCodeService);

        $client->request(
            'POST',
            '/api/user-login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CLIENT_AUTH' => 'header-value',
            ],
            content: json_encode([
                'publicId' => 'cid_corporate-id',
                'message' => 'ckey-example',
                'domain' => 'https://example.test',
                'userPublicId' => 'user-123',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        self::assertSame('process-123', json_decode((string) $client->getResponse()->getContent(), true)['domainProcessId']);
    }

    public function testLoginCallbackIsHandledByControllerThroughHttpKernel(): void
    {
        $client = static::createClient();

        $instanceRegistrationService = $this->createMock(InstanceRegistrationService::class);
        $instanceRegistrationService
            ->method('getInitializationState')
            ->willReturn(false);

        static::getContainer()->set(InstanceRegistrationService::class, $instanceRegistrationService);

        $callbackService = $this->createMock(UserProcessCallbackService::class);
        $callbackService
            ->expects(self::once())
            ->method('allowLoginProcess')
            ->willReturn(true);

        static::getContainer()->set(UserProcessCallbackService::class, $callbackService);

        $client->request(
            'POST',
            '/api/user-login/callback',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'signature' => base64_encode('signature'),
                'publicId' => 'public-id',
                'email' => 'user@example.test',
                'processId' => rtrim(base64_encode(str_repeat('a', 16)), '='),
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        self::assertSame([
            'status' => 'ok',
            'success' => true,
        ], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testUserLoginNewQrReturnsForbiddenForInvalidCsrfThroughHttpKernel(): void
    {
        $client = static::createClient();

        $instanceRegistrationService = $this->createMock(InstanceRegistrationService::class);
        $instanceRegistrationService
            ->method('getInitializationState')
            ->willReturn(false);

        static::getContainer()->set(InstanceRegistrationService::class, $instanceRegistrationService);

        $client->request(
            'POST',
            '/api/user-login/new-qr',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => 'invalid-token',
            ],
            content: json_encode([
                'domainProcessId' => 'process-123',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'Invalid CSRF token'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testUserLoginCheckReturnsForbiddenForInvalidCsrfThroughHttpKernel(): void
    {
        $client = static::createClient();

        $instanceRegistrationService = $this->createMock(InstanceRegistrationService::class);
        $instanceRegistrationService
            ->method('getInitializationState')
            ->willReturn(false);

        static::getContainer()->set(InstanceRegistrationService::class, $instanceRegistrationService);

        $client->request(
            'POST',
            '/api/user-login/check',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => 'invalid-token',
            ],
            content: json_encode([
                'domainProcessId' => 'process-123',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'Invalid CSRF token'], json_decode((string) $client->getResponse()->getContent(), true));
    }
}
