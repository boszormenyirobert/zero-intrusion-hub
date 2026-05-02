<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Shared;

use App\Attribute\PublicRoute;
use App\Controller\CredentialHub\Shared\RegistrationController;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class RegistrationControllerTest extends TestCase
{
    public function testSharedRegistrationQrIdentityRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(RegistrationController::class, 'sharedRegistrationQrIdentity');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testSharedRegistrationNewToEncryptRouteExplicitlyRequiresExtensionAuth(): void
    {
        $reflectionMethod = new \ReflectionMethod(RegistrationController::class, 'sharedRegistrationNewToEncrypt');

        self::assertCount(1, array_filter(
            $reflectionMethod->getAttributes(),
            static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'
        ));
    }

    public function testSharedRegistrationNewRouteExplicitlyRequiresExtensionAuth(): void
    {
        $reflectionMethod = new \ReflectionMethod(RegistrationController::class, 'sharedRegistrationNew');

        self::assertCount(1, array_filter(
            $reflectionMethod->getAttributes(),
            static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'
        ));
    }

    public function testSharedRegistrationStateRouteExplicitlyRequiresExtensionAuth(): void
    {
        $reflectionMethod = new \ReflectionMethod(RegistrationController::class, 'sharedRegistrationState');

        self::assertCount(1, array_filter(
            $reflectionMethod->getAttributes(),
            static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'
        ));
    }

    public function testSharedRegistrationStateForwardsRawPayloadWithExtensionHeader(): void
    {
        $request = Request::create('/api/credential-hub/shared/registration/state', 'POST', content: '{"processId":"pid"}');
        $request->headers->set('X-Extension-Auth', 'extension-header');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'shared_registration_state' => '{"processId":"pid"}',
                'X-Extension-Auth' => 'extension-header',
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $controller = new RegistrationController($this->createMock(LoggerInterface::class));
        $response = $controller->sharedRegistrationState($request, $forwardingService);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }
}