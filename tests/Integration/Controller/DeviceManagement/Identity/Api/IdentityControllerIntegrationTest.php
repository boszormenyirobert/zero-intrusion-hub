<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\DeviceManagement\Identity\Api;

use App\Service\User\BackendForwardingService;
use App\Service\Instance\HUB\InstanceRegistrationService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class IdentityControllerIntegrationTest extends WebTestCase
{
    public function testRecoverySettingsReturnsForwardedPayload(): void
    {
        $client = static::createClient();

        $instanceRegistrationService = $this->createMock(InstanceRegistrationService::class);
        $instanceRegistrationService
            ->method('getInitializationState')
            ->willReturn(false);

        static::getContainer()->set(InstanceRegistrationService::class, $instanceRegistrationService);

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with(self::callback(static function (array $payload): bool {
                return isset($payload['recoverySettings'])
                    && !isset($payload['X-Extension-Auth']);
            }))
            ->willReturn(new JsonResponse(['status' => 'ok']));

        static::getContainer()->set(BackendForwardingService::class, $forwardingService);

        $client->request(
            'POST',
            '/api/secret/recovery-settings',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'user@example.test',
                'phone' => '+3612345678',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        self::assertSame('ok', json_decode((string) $client->getResponse()->getContent(), true)['status']);
    }
}
