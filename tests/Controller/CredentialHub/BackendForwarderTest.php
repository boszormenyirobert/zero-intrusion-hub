<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub;

use App\Controller\CredentialHub\BackendForwarder;
use App\Service\Shared\ProcessKey;
use App\Service\Security\ExtensionAuthGuard;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class BackendForwarderTest extends TestCase
{
    public function testForwardReturnsUnauthorizedWhenExtensionHeaderIsRequired(): void
    {
        $request = Request::create('/api/test', 'POST', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'SecurityTest/1.0',
        ], content: '{"ok":true}');

        $service = $this->createMock(BackendForwardingService::class);
        $service->expects(self::never())->method('forwardRegistration');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(
                self::logicalOr(
                    self::equalTo('Forward request received'),
                    self::equalTo('Missing extension auth header')
                ),
                self::callback(static function (array $context): bool {
                    if (($context['request_id'] ?? null) === null) {
                        return false;
                    }

                    if (array_key_exists('remote_addr', $context) || array_key_exists('user_agent', $context)) {
                        return false;
                    }

                    if (($context['process'] ?? null) !== ProcessKey::ONE_TOUCH_STATE) {
                        return false;
                    }

                    return true;
                })
            );

        $response = BackendForwarder::forward($request, $service, $logger, ProcessKey::ONE_TOUCH_STATE);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Missing X-Extension-Auth header!'], json_decode((string) $response->getContent(), true));
    }

    public function testForwardUsesPolicyMapToDecodeJsonPayload(): void
    {
        $request = Request::create('/api/test', 'POST', content: '{"ok":true}');

        $service = $this->createMock(BackendForwardingService::class);
        $service
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                ProcessKey::ONE_TOUCH_QR_IDENTITY => ['ok' => true],
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info');
        $logger->method('debug');

        $response = BackendForwarder::forward($request, $service, $logger, ProcessKey::ONE_TOUCH_QR_IDENTITY);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }

    public function testForwardReturnsServerErrorWhenPolicyIsMissing(): void
    {
        $request = Request::create('/api/test', 'POST', content: '{"ok":true}');

        $service = $this->createMock(BackendForwardingService::class);
        $service->expects(self::never())->method('forwardRegistration');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $response = BackendForwarder::forward($request, $service, $logger, 'unknown_process');

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['error' => 'Forward policy missing'], json_decode((string) $response->getContent(), true));
    }

    public function testForwardReturnsBadRequestForEmptyBody(): void
    {
        $request = Request::create('/api/test', 'POST', content: '');

        $service = $this->createMock(BackendForwardingService::class);
        $service->expects(self::never())->method('forwardRegistration');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $response = BackendForwarder::forwardWithPolicy($request, $service, $logger, 'process_key', false, false);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'Empty request body'], json_decode((string) $response->getContent(), true));
    }

    public function testForwardReturnsBadRequestForInvalidJsonPayload(): void
    {
        $request = Request::create('/api/test', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{invalid');
        $service = $this->createMock(BackendForwardingService::class);
        $service->expects(self::never())->method('forwardRegistration');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $response = BackendForwarder::forwardWithPolicy($request, $service, $logger, 'process_key', false, true);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'Invalid JSON request body'], json_decode((string) $response->getContent(), true));
    }

    public function testForwardWithHmacDelegatesToForwardingService(): void
    {
        $request = Request::create('/api/test', 'POST', content: '{"ok":true}');
        $request->headers->set('X-Client-Auth', 'header');

        $service = $this->createMock(BackendForwardingService::class);
        $service
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'process_key' => ['ok' => true],
                'X-Extension-Auth' => 'header',
            ])
            ->willReturn(new JsonResponse(['status' => 'ok'], 200));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info');
        $logger->method('debug');

        $response = BackendForwarder::forwardWithHmac($request, $service, $logger, 'process_key', 'header');

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }

    public function testForwardUsesValidatedExtensionAuthRequestAttributeWhenPresent(): void
    {
        $request = Request::create('/api/test', 'POST', content: '{"ok":true}');
        $request->attributes->set(ExtensionAuthGuard::REQUEST_ATTRIBUTE, 'validated-extension-header');

        $service = $this->createMock(BackendForwardingService::class);
        $service
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'process_key' => ['ok' => true],
                'X-Extension-Auth' => 'validated-extension-header',
            ])
            ->willReturn(new JsonResponse(['status' => 'ok'], 200));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info');
        $logger->method('debug');

        $response = BackendForwarder::forwardWithPolicy($request, $service, $logger, 'process_key', true, true);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }

    public function testForwardReturnsBadGatewayForInvalidBackendJsonResponse(): void
    {
        $request = Request::create('/api/test', 'POST', content: '{"ok":true}');

        $service = $this->createMock(BackendForwardingService::class);
        $service
            ->expects(self::once())
            ->method('forwardRegistration')
            ->willReturn(new JsonResponse('{invalid', 200, [], true));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info');
        $logger->method('debug');
        $logger->expects(self::once())->method('error');

        $response = BackendForwarder::forwardWithPolicy($request, $service, $logger, 'process_key', false, false);

        self::assertSame(502, $response->getStatusCode());
        self::assertSame(['error' => 'Invalid backend response'], json_decode((string) $response->getContent(), true));
    }

    public function testForwardReturnsServiceUnavailableWhenTransportFails(): void
    {
        $request = Request::create('/api/test', 'POST', content: '{"ok":true}');

        $service = $this->createMock(BackendForwardingService::class);
        $service
            ->expects(self::once())
            ->method('forwardRegistration')
            ->willThrowException(new \RuntimeException('transport down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info');
        $logger->method('debug');
        $logger->expects(self::once())->method('error');

        $response = BackendForwarder::forwardWithPolicy($request, $service, $logger, 'process_key', false, true);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame(['error' => 'Backend unavailable'], json_decode((string) $response->getContent(), true));
    }
}