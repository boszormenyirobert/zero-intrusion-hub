<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\User\Registration\Api;

use App\Controller\User\Registration\Api\RegistrationController;
use App\Service\Security\ApiRateLimitService;
use App\Service\Instance\HUB\InstanceRegistrationService;
use App\Service\User\Callback\UserProcessCallbackService;
use App\Service\User\Registration\Api\RegistrationApiRequestMapper;
use App\Service\User\UserQrCodeService;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationControllerIntegrationTest extends WebTestCase
{
    public function testRegistrationCallbackReturnsForbiddenWhenRejected(): void
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
            ->method('createRegisteredUser')
            ->willReturn(false);

        static::getContainer()->set(UserProcessCallbackService::class, $callbackService);

        $controller = new RegistrationController(
            new NullLogger(),
            $this->createMock(UserQrCodeService::class),
            $callbackService,
            new RegistrationApiRequestMapper(new NullLogger()),
            $this->createMock(ApiRateLimitService::class)
        );
        $controller->setContainer(static::getContainer());

        static::getContainer()->set(RegistrationController::class, $controller);

        $client->request(
            'POST',
            '/api/registration/callback',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'signature' => 'signature',
                'publicId' => 'public-id',
                'email' => 'user@example.test',
                'registrationProcessId' => 'registration-123',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame('Registration rejected.', json_decode((string) $client->getResponse()->getContent(), true)['message']);
    }
}
