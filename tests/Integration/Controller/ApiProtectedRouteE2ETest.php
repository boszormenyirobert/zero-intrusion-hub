<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DTO\QrCodeResponseDTO;
use App\Service\Device\Identity\FirstSecretInstanceSettingsHandler;
use App\Service\Instance\HUB\InstanceRegistrationService;
use App\Service\User\BackendForwardingService;
use App\Service\User\UserQrCodeService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiProtectedRouteE2ETest extends WebTestCase
{
    public function testNfcUsersAndDecryptEndpointsForwardValidatedClientAuthHeader(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->disableInitializationStateGuard();

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::exactly(2))
            ->method('forwardRegistration')
            ->withConsecutive(
                [self::callback(static fn (array $payload): bool => $payload['api_nfc_users'] === ['card' => 'first'] && $payload['X-Extension-Auth'] === 'client-auth-header')],
                [self::callback(static fn (array $payload): bool => $payload['api_nfc_decrypt'] === ['card' => 'second'] && $payload['X-Extension-Auth'] === 'client-auth-header')]
            )
            ->willReturnOnConsecutiveCalls(new JsonResponse(['status' => 'ok-users']), new JsonResponse(['status' => 'ok-decrypt']));

        static::getContainer()->set(BackendForwardingService::class, $forwardingService);

        $client->request('POST', '/api/nfc/users', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CLIENT_AUTH' => 'client-auth-header'], content: '{"card":"first"}');
        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok-users'], json_decode((string) $client->getResponse()->getContent(), true));

        $client->request('POST', '/api/nfc/decrypt', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CLIENT_AUTH' => 'client-auth-header'], content: '{"card":"second"}');
        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok-decrypt'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testFirstSecretEndpointDoesNotRequireOrForwardClientAuthHeader(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->disableInitializationStateGuard();

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'firstSecret' => 'no-data',
            ])
            ->willReturn(new JsonResponse(['privateSecret' => ['publicId' => 'public-id']])) ;

        $handler = $this->createMock(FirstSecretInstanceSettingsHandler::class);
        $handler->expects(self::once())->method('handle')->with('public-id');

        static::getContainer()->set(BackendForwardingService::class, $forwardingService);
        static::getContainer()->set(FirstSecretInstanceSettingsHandler::class, $handler);

        $client->request('GET', '/api/secret/new');

        self::assertResponseIsSuccessful();
        self::assertSame('public-id', json_decode((string) $client->getResponse()->getContent(), true)['privateSecret']['publicId']);
    }

    public function testUserRegistrationEndpointReturnsQrPayloadThroughFullHttpChain(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->disableInitializationStateGuard();

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::once())
            ->method('getQrCode')
            ->willReturn(QrCodeResponseDTO::fromArray([
                'registrationProcessId' => 'registration-123',
                'qrCode' => 'qr-code',
            ]));

        static::getContainer()->set(UserQrCodeService::class, $qrCodeService);

        $client->request('POST', '/api/user-registration', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CLIENT_AUTH' => 'client-auth-header',
        ], content: json_encode([
            'publicId' => 'cid_corporate-id',
            'message' => 'ckey-example',
            'domain' => 'example.test',
            'userPublicId' => 'userPublicIdValue',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertSame('registration-123', json_decode((string) $client->getResponse()->getContent(), true)['registrationProcessId']);
    }

    private function disableInitializationStateGuard(): void
    {
        $instanceRegistrationService = $this->createMock(InstanceRegistrationService::class);
        $instanceRegistrationService->method('getInitializationState')->willReturn(false);

        static::getContainer()->set(InstanceRegistrationService::class, $instanceRegistrationService);
    }
}
