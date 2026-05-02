<?php

declare(strict_types=1);

namespace App\Tests\Controller\DeviceManagement\Nfc\Api;

use App\Attribute\ClientAuthRequired;
use App\Controller\DeviceManagement\Nfc\Api\NfcController;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class NfcControllerTest extends TestCase
{
    public function testGetNfcUsersRouteRequiresClientAuthAttribute(): void
    {
        $reflectionMethod = new \ReflectionMethod(NfcController::class, 'getNfcUsers');

        self::assertNotEmpty($reflectionMethod->getAttributes(ClientAuthRequired::class));
    }

    public function testDecryptNfcCardDataRouteRequiresClientAuthAttribute(): void
    {
        $reflectionMethod = new \ReflectionMethod(NfcController::class, 'decryptNfcCardData');

        self::assertNotEmpty($reflectionMethod->getAttributes(ClientAuthRequired::class));
    }

    public function testGetNfcUsersRejectsMissingClientAuthHeader(): void
    {
        $request = Request::create('/api/nfc/users', 'POST', content: '{"user":true}');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::never())
            ->method('forwardRegistration');

        $controller = new NfcController($this->createMock(LoggerInterface::class));
        $response = $controller->getNfcUsers($request, $forwardingService);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Missing X-Client-Auth header!'], json_decode((string) $response->getContent(), true));
    }

    public function testGetNfcUsersForwardsHmacProtectedPayload(): void
    {
        $request = Request::create('/api/nfc/users', 'POST', content: '{"user":true}');
        $request->headers->set('x-client-auth', 'client-auth-header');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'api_nfc_users' => ['user' => true],
                'X-Extension-Auth' => 'client-auth-header',
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $controller = new NfcController($this->createMock(LoggerInterface::class));
        $response = $controller->getNfcUsers($request, $forwardingService);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }

    public function testGetNfcUsersUsesValidatedRequestAttributeWhenHeaderWasResolvedUpstream(): void
    {
        $request = Request::create('/api/nfc/users', 'POST', content: '{"user":true}');
        $request->attributes->set('_client_auth_header', 'validated-upstream-header');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'api_nfc_users' => ['user' => true],
                'X-Extension-Auth' => 'validated-upstream-header',
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $controller = new NfcController($this->createMock(LoggerInterface::class));
        $response = $controller->getNfcUsers($request, $forwardingService);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }

    public function testDecryptNfcCardDataForwardsHmacProtectedPayload(): void
    {
        $request = Request::create('/api/nfc/decrypt', 'POST', content: '{"card":"payload"}');
        $request->headers->set('x-client-auth', 'client-auth-header');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'api_nfc_decrypt' => ['card' => 'payload'],
                'X-Extension-Auth' => 'client-auth-header',
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $controller = new NfcController($this->createMock(LoggerInterface::class));
        $response = $controller->decryptNfcCardData($request, $forwardingService);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }

    public function testDecryptNfcCardDataUsesValidatedRequestAttributeWhenHeaderWasResolvedUpstream(): void
    {
        $request = Request::create('/api/nfc/decrypt', 'POST', content: '{"card":"payload"}');
        $request->attributes->set('_client_auth_header', 'validated-upstream-header');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'api_nfc_decrypt' => ['card' => 'payload'],
                'X-Extension-Auth' => 'validated-upstream-header',
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $controller = new NfcController($this->createMock(LoggerInterface::class));
        $response = $controller->decryptNfcCardData($request, $forwardingService);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }

    public function testDecryptNfcCardDataRejectsMissingClientAuthHeader(): void
    {
        $request = Request::create('/api/nfc/decrypt', 'POST', content: '{"card":"payload"}');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::never())
            ->method('forwardRegistration');

        $controller = new NfcController($this->createMock(LoggerInterface::class));
        $response = $controller->decryptNfcCardData($request, $forwardingService);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Missing X-Client-Auth header!'], json_decode((string) $response->getContent(), true));
    }
}