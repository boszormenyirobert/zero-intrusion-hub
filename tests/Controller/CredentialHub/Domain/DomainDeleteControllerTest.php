<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Domain;

use App\Attribute\PublicRoute;
use App\Controller\CredentialHub\Domain\Delete\DomainDeleteController;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class DomainDeleteControllerTest extends TestCase
{
    public function testDomainDeleteQrIdentityRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(DomainDeleteController::class, 'domainDeleteQrIdentity');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testDomainDeleteCredentialRouteExplicitlyRequiresExtensionAuth(): void
    {
        $reflectionMethod = new \ReflectionMethod(DomainDeleteController::class, 'domainDeleteCredential');

        self::assertCount(1, array_filter(
            $reflectionMethod->getAttributes(),
            static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'
        ));
    }

    public function testDomainDeleteStateRouteExplicitlyRequiresExtensionAuth(): void
    {
        $reflectionMethod = new \ReflectionMethod(DomainDeleteController::class, 'domainDeleteState');

        self::assertCount(1, array_filter(
            $reflectionMethod->getAttributes(),
            static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'
        ));
    }

    public function testDomainDeleteCredentialForwardsRawPayloadWithExtensionHeader(): void
    {
        $request = Request::create('/api/credential-hub/domain/delete/credential', 'POST', content: '{"delete":true}');
        $request->headers->set('X-Extension-Auth', 'extension-header');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'domain_delete_credential' => '{"delete":true}',
                'X-Extension-Auth' => 'extension-header',
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $controller = new DomainDeleteController($this->createMock(LoggerInterface::class));
        $response = $controller->domainDeleteCredential($request, $forwardingService);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }
}