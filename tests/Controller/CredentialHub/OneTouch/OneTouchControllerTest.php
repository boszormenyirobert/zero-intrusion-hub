<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\OneTouch;

use App\Attribute\PublicRoute;
use App\Controller\CredentialHub\OneTouch\OneTouchController;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class OneTouchControllerTest extends TestCase
{
    public function testOneTouchQrIdentityRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(OneTouchController::class, 'oneTouchQrIdentity');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testOneTouchIdentifierRouteExplicitlyRequiresExtensionAuth(): void
    {
        $reflectionMethod = new \ReflectionMethod(OneTouchController::class, 'oneTouchIdentifier');

        self::assertCount(1, array_filter(
            $reflectionMethod->getAttributes(),
            static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'
        ));
    }

    public function testOneTouchStateRouteExplicitlyRequiresExtensionAuth(): void
    {
        $reflectionMethod = new \ReflectionMethod(OneTouchController::class, 'oneTouchState');

        self::assertCount(1, array_filter(
            $reflectionMethod->getAttributes(),
            static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'
        ));
    }

    public function testOneTouchQrIdentityForwardsDecodedPayloadWithoutExtensionHeader(): void
    {
        $request = Request::create('/api/credential-hub/one-touch/qr-identity', 'POST', content: '{"secure":true}');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'one_touch_qr_identity' => ['secure' => true],
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $controller = new OneTouchController($this->createMock(LoggerInterface::class));
        $response = $controller->oneTouchQrIdentity($request, $forwardingService);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }
}