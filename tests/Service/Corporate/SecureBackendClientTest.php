<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Helper\AuthorizationHelper;
use App\Service\Corporate\SecureBackendClient;
use App\Service\Shared\RouteService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SecureBackendClientTest extends TestCase
{
    public function testPostReturnsErrorResponseWhenRouteMappingMissing(): void
    {
        $routeService = $this->createMock(RouteService::class);
        $routeService
            ->expects(self::once())
            ->method('mapRoute')
            ->with(['process' => ['payload' => true]])
            ->willReturn('');

        $authorizationHelper = $this->createAuthorizationHelper();

        $params = $this->createMock(ContainerBagInterface::class);
        $params->expects(self::never())->method('get');

        $client = new SecureBackendClient(
            $routeService,
            $authorizationHelper,
            $params,
            $this->createMock(LoggerInterface::class)
        );

        $response = $client->post(['process' => ['payload' => true]]);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['error' => 'Backend route mapping missing.'], json_decode((string) $response->getContent(), true));
    }

    public function testPostBuildsSecureRequestAndForwardsExtensionHeader(): void
    {
        $routeService = $this->createMock(RouteService::class);
        $routeService
            ->expects(self::once())
            ->method('mapRoute')
            ->with([
                'process' => ['payload' => true],
                'X-Extension-Auth' => 'extension-token',
            ])
            ->willReturn('https://example.test/backend');

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('getHeaders')->with(false)->willReturn([
            'content-type' => ['application/json'],
            'x-auth' => ['response-auth'],
        ]);
        $httpResponse->method('getContent')->with(false)->willReturn('{"success":true}');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://example.test/backend',
                self::callback(static function (array $options): bool {
                    $headers = $options['headers'] ?? [];
                    $body = $options['body'] ?? null;

                    if (($headers['X-Extension-Auth'] ?? null) !== 'extension-token') {
                        return false;
                    }

                    if (!isset($headers['X-Auth']) || !is_string($headers['X-Auth']) || !str_starts_with($headers['X-Auth'], 'HMAC ')) {
                        return false;
                    }

                    if (!is_string($body)) {
                        return false;
                    }

                    $decoded = json_decode($body, true);

                    return is_array($decoded)
                        && array_key_exists('zeroIntrusionProyApi', $decoded)
                        && is_string($decoded['zeroIntrusionProyApi'])
                        && $decoded['zeroIntrusionProyApi'] !== ''
                        && array_key_exists('iv', $decoded)
                        && is_string($decoded['iv'])
                        && $decoded['iv'] !== '';
                })
            )
            ->willReturn($httpResponse);

        $authorizationHelper = $this->createAuthorizationHelper($httpClient);

        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->expects(self::once())
            ->method('get')
            ->with('DATA_HASH_SECRET')
            ->willReturn(str_repeat('a', 32));

        $client = new SecureBackendClient(
            $routeService,
            $authorizationHelper,
            $params,
            $this->createMock(LoggerInterface::class)
        );

        $response = $client->post([
            'process' => ['payload' => true],
            'X-Extension-Auth' => 'extension-token',
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => true], json_decode((string) $response->getContent(), true));
        self::assertSame('response-auth', $response->headers->get('X-Auth'));
    }

    public function testPostReturnsResponseEvenWhenBackendJsonCannotBeDecodedForLogging(): void
    {
        $routeService = $this->createMock(RouteService::class);
        $routeService->method('mapRoute')->willReturn('https://example.test/backend');

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('getHeaders')->with(false)->willReturn([
            'content-type' => ['application/json'],
        ]);
        $httpResponse->method('getContent')->with(false)->willReturn('{invalid');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('request')
            ->willReturn($httpResponse);

        $authorizationHelper = $this->createAuthorizationHelper($httpClient);

        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->with('DATA_HASH_SECRET')->willReturn(str_repeat('a', 32));

        $client = new SecureBackendClient(
            $routeService,
            $authorizationHelper,
            $params,
            $this->createMock(LoggerInterface::class)
        );

        $response = $client->post(['process' => ['payload' => true]]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Non-JSON or invalid response', json_decode((string) $response->getContent(), true)['error']);
    }

    private function createAuthorizationHelper(?HttpClientInterface $httpClient = null): AuthorizationHelper
    {
        return new AuthorizationHelper(
            $httpClient ?? $this->createMock(HttpClientInterface::class),
            'service-secret',
            'service-key',
            $this->createMock(LoggerInterface::class)
        );
    }
}
