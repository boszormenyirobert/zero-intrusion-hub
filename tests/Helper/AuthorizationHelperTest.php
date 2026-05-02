<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Helper\AuthorizationHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpClientResponseInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AuthorizationHelperTest extends TestCase
{
    private const SERVICE_SECRET = 'service-secret';
    private const SERVICE_KEY = 'service-key';

    public function testBuildRequestIncludesForwardedExtensionHeader(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->with(false)->willReturn([
            'content-type' => ['application/json'],
            'x-auth' => ['HMAC service-key:signature:123'],
        ]);
        $response->method('getContent')->with(false)->willReturn('{"success":true}');

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://example.test',
                self::callback(function (array $options): bool {
                    self::assertSame('extension-header', $options['headers']['X-Extension-Auth']);
                    self::assertSame('auth', $options['headers']['X-Auth']);
                    self::assertSame(10.0, $options['timeout']);

                    return true;
                })
            )
            ->willReturn($response);

        $helper = new AuthorizationHelper(
            $client,
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $result = $helper->buildRequest('auth', 'encrypted', 'https://example.test', 'iv-base64', 'extension-header');

        self::assertSame(200, $result->getStatusCode());
        self::assertSame('HMAC service-key:signature:123', $result->headers->get('X-Auth'));
    }

    public function testBuildRequestReturnsErrorResponseForInvalidJsonBody(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->with(false)->willReturn([
            'content-type' => ['application/json'],
        ]);
        $response->method('getContent')->with(false)->willReturn('{invalid');

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $helper = new AuthorizationHelper(
            $client,
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $result = $helper->buildRequest('auth', 'encrypted', 'https://example.test', 'iv-base64');

        self::assertSame(403, $result->getStatusCode());
        $decoded = json_decode((string) $result->getContent(), true);

        self::assertSame([
            'error' => 'Non-JSON or invalid response',
            'status' => 403,
        ], $decoded);
        self::assertIsArray($decoded);
        self::assertArrayNotHasKey('raw', $decoded);
    }

    public function testValidateAuthorizationHeaderRejectsInvalidJsonPayload(): void
    {
        $helper = new AuthorizationHelper(
            $this->createMock(HttpClientInterface::class),
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $response = new JsonResponse('{invalid', 200, ['X-Auth' => 'HMAC service-key:signature:123'], true);

        $result = $helper->validateAuthorizationHeader((object) [
            'corporateIdentity' => 'encrypted',
        ], $response);

        self::assertSame([
            'success' => false,
            'error' => 'Invalid response payload',
        ], $result);
    }

    public function testValidateAuthorizationHeaderRejectsMissingResponseHeader(): void
    {
        $helper = new AuthorizationHelper(
            $this->createMock(HttpClientInterface::class),
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $response = new JsonResponse([
            'corporateIdentity' => 'encrypted',
            'iv' => 'MTIzNDU2Nzg5MDEyMzQ1Ng==',
        ]);

        $result = $helper->validateAuthorizationHeader((object) [
            'corporateIdentity' => 'encrypted',
        ], $response);

        self::assertSame([
            'success' => false,
            'error' => 'Missing X-Auth header',
        ], $result);
    }

    public function testValidateAuthorizationHeaderAcceptsFreshTimestamp(): void
    {
        $helper = new AuthorizationHelper(
            $this->createMock(HttpClientInterface::class),
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $encrypted = 'encrypted';
        $iv = 'MTIzNDU2Nzg5MDEyMzQ1Ng==';

        $response = new JsonResponse([
            'corporateIdentity' => $encrypted,
            'iv' => $iv,
        ], 200, [
            'X-Auth' => $this->createResponseAuthHeader($encrypted, $iv, time()),
        ]);

        $result = $helper->validateAuthorizationHeader((object) [
            'corporateIdentity' => $encrypted,
        ], $response);

        self::assertSame(['success' => true], $result);
    }

    public function testValidateAuthorizationHeaderRejectsExpiredTimestamp(): void
    {
        $helper = new AuthorizationHelper(
            $this->createMock(HttpClientInterface::class),
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $encrypted = 'encrypted';
        $iv = 'MTIzNDU2Nzg5MDEyMzQ1Ng==';

        $response = new JsonResponse([
            'corporateIdentity' => $encrypted,
            'iv' => $iv,
        ], 200, [
            'X-Auth' => $this->createResponseAuthHeader($encrypted, $iv, time() - 3600),
        ]);

        $result = $helper->validateAuthorizationHeader((object) [
            'corporateIdentity' => $encrypted,
        ], $response);

        self::assertSame([
            'success' => false,
            'error' => 'Expired X-Auth timestamp',
        ], $result);
    }

    public function testValidateAuthorizationHeaderRejectsNonNumericTimestamp(): void
    {
        $helper = new AuthorizationHelper(
            $this->createMock(HttpClientInterface::class),
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $response = new JsonResponse([
            'corporateIdentity' => 'encrypted',
            'iv' => 'MTIzNDU2Nzg5MDEyMzQ1Ng==',
        ], 200, [
            'X-Auth' => 'HMAC ' . self::SERVICE_KEY . ':signature:not-a-timestamp',
        ]);

        $result = $helper->validateAuthorizationHeader((object) [
            'corporateIdentity' => 'encrypted',
        ], $response);

        self::assertSame([
            'success' => false,
            'error' => 'Invalid X-Auth timestamp',
        ], $result);
    }

    public function testBuildRequestSanitizesClientExceptionResponse(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->willThrowException(new class($this->createMock(HttpClientResponseInterface::class)) extends \RuntimeException implements ClientExceptionInterface {
                public function __construct(
                    private HttpClientResponseInterface $response
                ) {
                    parent::__construct('upstream secret body', 403);
                }

                public function getResponse(): HttpClientResponseInterface
                {
                    return $this->response;
                }
            });

        $helper = new AuthorizationHelper(
            $client,
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $result = $helper->buildRequest('auth', 'encrypted', 'https://example.test', 'iv-base64');

        self::assertSame(403, $result->getStatusCode());
        self::assertSame([
            'error' => 'Request failed',
            'status' => 403,
        ], json_decode((string) $result->getContent(), true));
    }

    public function testBuildRequestHandlesServerExceptionResponse(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->willThrowException(new class($this->createMock(HttpClientResponseInterface::class)) extends \RuntimeException implements ServerExceptionInterface {
                public function __construct(
                    private HttpClientResponseInterface $response
                ) {
                    parent::__construct('upstream 500 body', 500);
                }

                public function getResponse(): HttpClientResponseInterface
                {
                    return $this->response;
                }
            });

        $helper = new AuthorizationHelper(
            $client,
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $result = $helper->buildRequest('auth', 'encrypted', 'https://example.test', 'iv-base64');

        self::assertSame(502, $result->getStatusCode());
        self::assertSame([
            'error' => 'Upstream request failed',
            'status' => 502,
        ], json_decode((string) $result->getContent(), true));
    }

    public function testBuildRequestHandlesTransportExceptionResponse(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->willThrowException(new class() extends \RuntimeException implements TransportExceptionInterface {
                public function __construct()
                {
                    parent::__construct('connection timeout');
                }
            });

        $helper = new AuthorizationHelper(
            $client,
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $result = $helper->buildRequest('auth', 'encrypted', 'https://example.test', 'iv-base64');

        self::assertSame(503, $result->getStatusCode());
        self::assertSame([
            'error' => 'Upstream transport failed',
            'status' => 503,
        ], json_decode((string) $result->getContent(), true));
    }

    public function testBuildRequestHandlesRedirectionExceptionResponse(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->willThrowException(new class($this->createMock(HttpClientResponseInterface::class)) extends \RuntimeException implements RedirectionExceptionInterface {
                public function __construct(
                    private HttpClientResponseInterface $response
                ) {
                    parent::__construct('too many redirects', 302);
                }

                public function getResponse(): HttpClientResponseInterface
                {
                    return $this->response;
                }
            });

        $helper = new AuthorizationHelper(
            $client,
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $result = $helper->buildRequest('auth', 'encrypted', 'https://example.test', 'iv-base64');

        self::assertSame(502, $result->getStatusCode());
        self::assertSame([
            'error' => 'Upstream request failed',
            'status' => 502,
        ], json_decode((string) $result->getContent(), true));
    }

    public function testBuildRequestHandlesDecodingExceptionResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->with(false)->willThrowException(new class() extends \RuntimeException implements DecodingExceptionInterface {
            public function __construct()
            {
                parent::__construct('header decode failed');
            }
        });

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $helper = new AuthorizationHelper(
            $client,
            self::SERVICE_SECRET,
            self::SERVICE_KEY,
            $this->createMock(LoggerInterface::class)
        );

        $result = $helper->buildRequest('auth', 'encrypted', 'https://example.test', 'iv-base64');

        self::assertSame(502, $result->getStatusCode());
        self::assertSame([
            'error' => 'Upstream response handling failed',
            'status' => 502,
        ], json_decode((string) $result->getContent(), true));
    }

    private function createResponseAuthHeader(string $encryptedData, string $ivBase64, int $timestamp): string
    {
        $signature = hash_hmac('sha256', $encryptedData . '|' . $ivBase64, self::SERVICE_SECRET);

        return 'HMAC ' . self::SERVICE_KEY . ':' . $signature . ':' . $timestamp;
    }
}