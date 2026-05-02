<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Helper\AuthorizationHelper;
use App\Service\Corporate\AuthorizedBackendResponseService;
use App\Service\Crypters\CrypterService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AuthorizedBackendResponseServiceTest extends TestCase
{
    public function testDecodeReturnsDecryptedPayloadWhenAuthorizationIsValid(): void
    {
        $params = $this->createParams();
        $authorizationHelper = $this->createAuthorizationHelper();
        $service = new AuthorizedBackendResponseService(
            $this->createMock(LoggerInterface::class),
            $authorizationHelper,
            $params
        );

        $corporateIdentity = (new CrypterService([
            'success' => true,
            'userValidation' => 'ok',
        ], $params))->encryptData();

        $response = new JsonResponse([
            'corporateIdentity' => $corporateIdentity,
            'iv' => 'iv-base64-value',
        ], 200, [
            'X-Auth' => $authorizationHelper->getAuthHeader($corporateIdentity, 'iv-base64-value'),
        ]);

        self::assertSame([
            'success' => true,
            'userValidation' => 'ok',
        ], $service->decode($response));
    }

    public function testDecodeThrowsForInvalidBackendJson(): void
    {
        $service = new AuthorizedBackendResponseService(
            $this->createMock(LoggerInterface::class),
            $this->createAuthorizationHelper(),
            $this->createParams()
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Invalid backend response payload.');

        $service->decode(new JsonResponse('{invalid', 200, [], true));
    }

    public function testDecodeThrowsForUpstreamErrorPayloadWithoutEncryptedData(): void
    {
        $service = new AuthorizedBackendResponseService(
            $this->createMock(LoggerInterface::class),
            $this->createAuthorizationHelper(),
            $this->createParams()
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Backend error: upstream failed');

        $service->decode(new JsonResponse([
            'error' => 'upstream failed',
        ]));
    }

    public function testDecodeThrowsWhenAuthorizationValidationFails(): void
    {
        $service = new AuthorizedBackendResponseService(
            $this->createMock(LoggerInterface::class),
            $this->createAuthorizationHelper(),
            $this->createParams()
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Missing X-Auth header');

        $service->decode(new JsonResponse([
            'corporateIdentity' => 'encrypted',
            'iv' => 'iv-base64-value',
        ]));
    }

    private function createAuthorizationHelper(): AuthorizationHelper
    {
        return new AuthorizationHelper(
            $this->createMock(HttpClientInterface::class),
            'service-secret',
            'service-key',
            $this->createMock(LoggerInterface::class)
        );
    }

    private function createParams(): ContainerBagInterface
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->with('DATA_HASH_SECRET')->willReturn(str_repeat('a', 32));

        return $params;
    }
}
