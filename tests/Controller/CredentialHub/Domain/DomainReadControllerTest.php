<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Domain;

use App\Attribute\PublicRoute;
use App\Controller\CredentialHub\Domain\Read\DomainReadController;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class DomainReadControllerTest extends TestCase
{
    public function testDomainReadQrIdentityRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(DomainReadController::class, 'domainReadQrIdentity');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testDomainReadCredentialEncryptedRouteExplicitlyRequiresExtensionAuth(): void
    {
        $reflectionMethod = new \ReflectionMethod(DomainReadController::class, 'domainReadCredentialEncrypted');

        self::assertCount(1, array_filter(
            $reflectionMethod->getAttributes(),
            static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'
        ));
    }

    public function testDomainReadCredentialRouteExplicitlyRequiresExtensionAuth(): void
    {
        $reflectionMethod = new \ReflectionMethod(DomainReadController::class, 'domainReadCredential');

        self::assertCount(1, array_filter(
            $reflectionMethod->getAttributes(),
            static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'
        ));
    }

    public function testDomainReadStateRouteExplicitlyRequiresExtensionAuth(): void
    {
        $reflectionMethod = new \ReflectionMethod(DomainReadController::class, 'domainReadState');

        self::assertCount(1, array_filter(
            $reflectionMethod->getAttributes(),
            static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'
        ));
    }

    public function testDomainReadCredentialEncryptedForwardsDecodedPayloadWithExtensionHeader(): void
    {
        $request = Request::create('/api/credential-hub/domain/read/credential/decrypted', 'POST', content: '{"credential":true}');
        $request->headers->set('X-Extension-Auth', 'extension-header');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'domain_read_credential_encrypted' => ['credential' => true],
                'X-Extension-Auth' => 'extension-header',
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $controller = new DomainReadController($this->createMock(LoggerInterface::class));
        $response = $controller->domainReadCredentialEncrypted($request, $forwardingService);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }
}