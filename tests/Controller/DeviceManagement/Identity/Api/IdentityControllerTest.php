<?php

declare(strict_types=1);

namespace App\Tests\Controller\DeviceManagement\Identity\Api;

use App\Attribute\PublicRoute;
use App\Controller\DeviceManagement\Identity\Api\IdentityController;
use App\Service\Device\Identity\Api\IdentityApiRequestMapper;
use App\Service\Device\Identity\FirstSecretInstanceSettingsHandler;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class IdentityControllerTest extends TestCase
{
    public function testRequestFirstSecretRouteIsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(IdentityController::class, 'requestFirstSecret');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testRequestRecoverySettingsRouteIsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(IdentityController::class, 'requestRecoverySettings');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testRequestFirstSecretDoesNotRequireClientAuthHeader(): void
    {
        $request = Request::create('/api/secret/new', 'GET');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'firstSecret' => 'no-data',
            ])
            ->willReturn(new JsonResponse([
                'privateSecret' => [
                    'publicId' => 'device-public-id',
                ],
            ]));

        $handler = $this->createMock(FirstSecretInstanceSettingsHandler::class);
        $handler
            ->expects(self::once())
            ->method('handle')
            ->with('device-public-id');

        $controller = new IdentityController();
        $response = $controller->requestFirstSecret($request, $forwardingService, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestFirstSecretForwardsRequestAndUpdatesInstanceSettings(): void
    {
        $request = Request::create('/api/secret/new', 'GET');
        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'firstSecret' => 'no-data',
            ])
            ->willReturn(new JsonResponse([
                'privateSecret' => [
                    'publicId' => 'device-public-id',
                ],
            ]));

        $handler = $this->createMock(FirstSecretInstanceSettingsHandler::class);
        $handler
            ->expects(self::once())
            ->method('handle')
            ->with('device-public-id');

        $controller = new IdentityController();
        $response = $controller->requestFirstSecret($request, $forwardingService, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestFirstSecretHandlesInvalidBackendJsonGracefully(): void
    {
        $request = Request::create('/api/secret/new', 'GET');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'firstSecret' => 'no-data',
            ])
            ->willReturn(new JsonResponse('{invalid', 200, [], true));

        $handler = $this->createMock(FirstSecretInstanceSettingsHandler::class);
        $handler
            ->expects(self::once())
            ->method('handle')
            ->with(null);

        $controller = new IdentityController();
        $response = $controller->requestFirstSecret($request, $forwardingService, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestRecoverySettingsDoesNotRequireClientAuthHeader(): void
    {
        $request = Request::create('/api/secret/recovery-settings', 'POST', content: json_encode([
            'email' => 'user@example.test',
        ], JSON_THROW_ON_ERROR));

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with(self::callback(static function (array $payload): bool {
                return isset($payload['recoverySettings'])
                    && !isset($payload['X-Extension-Auth'])
                    && is_object($payload['recoverySettings'])
                    && $payload['recoverySettings']->email === 'user@example.test';
            }))
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $requestMapper = $this->createMock(IdentityApiRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('mapRecoverySettingsPayload')
            ->with($request)
            ->willReturn((object) [
                'email' => 'user@example.test',
            ]);

        $controller = new IdentityController();
        $response = $controller->requestRecoverySettings($request, $forwardingService, $requestMapper);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }

    public function testRequestRecoverySettingsForwardsDecodedPayload(): void
    {
        $request = Request::create('/api/secret/recovery-settings', 'POST', content: json_encode([
            'email' => 'user@example.test',
            'phone' => '+3612345678',
        ], JSON_THROW_ON_ERROR));
        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with(self::callback(static function (array $payload): bool {
                return isset($payload['recoverySettings'])
                    && !isset($payload['X-Extension-Auth'])
                    && is_object($payload['recoverySettings'])
                    && $payload['recoverySettings']->email === 'user@example.test';
            }))
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $requestMapper = $this->createMock(IdentityApiRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('mapRecoverySettingsPayload')
            ->with($request)
            ->willReturn((object) [
                'email' => 'user@example.test',
                'phone' => '+3612345678',
            ]);

        $controller = new IdentityController();
        $response = $controller->requestRecoverySettings($request, $forwardingService, $requestMapper);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }

}
