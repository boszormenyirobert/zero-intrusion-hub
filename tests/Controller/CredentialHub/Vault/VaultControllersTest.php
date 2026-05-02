<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Vault;

use App\Attribute\PublicRoute;
use App\Controller\CredentialHub\Vault\Delete\VaultDeleteController;
use App\Controller\CredentialHub\Vault\Edit\VaultEditController;
use App\Controller\CredentialHub\Vault\Read\VaultReadController;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class VaultControllersTest extends TestCase
{
    public function testVaultReadRoutePoliciesAreExplicit(): void
    {
        self::assertNotEmpty((new \ReflectionMethod(VaultReadController::class, 'vaultReadQrIdentity'))->getAttributes(PublicRoute::class));
        self::assertCount(1, array_filter((new \ReflectionMethod(VaultReadController::class, 'vaultReadCredentialEncrypted'))->getAttributes(), static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'));
        self::assertCount(1, array_filter((new \ReflectionMethod(VaultReadController::class, 'vaultReadCredential'))->getAttributes(), static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'));
        self::assertCount(1, array_filter((new \ReflectionMethod(VaultReadController::class, 'vaultReadState'))->getAttributes(), static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'));
    }

    public function testVaultEditRoutePoliciesAreExplicit(): void
    {
        self::assertNotEmpty((new \ReflectionMethod(VaultEditController::class, 'vaultEditQrIdentity'))->getAttributes(PublicRoute::class));
        self::assertCount(1, array_filter((new \ReflectionMethod(VaultEditController::class, 'vaultEditCredential'))->getAttributes(), static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'));
        self::assertCount(1, array_filter((new \ReflectionMethod(VaultEditController::class, 'vaultEditState'))->getAttributes(), static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'));
    }

    public function testVaultDeleteRoutePoliciesAreExplicit(): void
    {
        self::assertNotEmpty((new \ReflectionMethod(VaultDeleteController::class, 'vaultDeleteQrIdentity'))->getAttributes(PublicRoute::class));
        self::assertCount(1, array_filter((new \ReflectionMethod(VaultDeleteController::class, 'vaultDeleteCredential'))->getAttributes(), static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'));
        self::assertCount(1, array_filter((new \ReflectionMethod(VaultDeleteController::class, 'vaultDeleteState'))->getAttributes(), static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === 'App\\Attribute\\ExtensionAuthRequired'));
    }

    public function testVaultReadQrIdentityForwardsDecodedPayload(): void
    {
        $request = Request::create('/api/credential-hub/vault/read/qr-identity', 'POST', content: '{"domain":"example.test"}');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'vault_read_qr_identity' => ['domain' => 'example.test'],
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $controller = new VaultReadController($this->createMock(LoggerInterface::class));
        $response = $controller->vaultReadQrIdentity($forwardingService, $request);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }

    public function testVaultEditCredentialForwardsRawPayloadWithExtensionHeader(): void
    {
        $request = Request::create('/api/credential-hub/vault/edit/credential', 'POST', content: '{"credential":true}');
        $request->headers->set('X-Extension-Auth', 'extension-header');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'vault_edit_credential' => '{"credential":true}',
                'X-Extension-Auth' => 'extension-header',
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $controller = new VaultEditController($this->createMock(LoggerInterface::class));
        $response = $controller->vaultEditCredential($request, $forwardingService);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }

    public function testVaultDeleteStateForwardsRawPayloadWithExtensionHeader(): void
    {
        $request = Request::create('/api/credential-hub/vault/delete/state', 'POST', content: '{"processId":"pid"}');
        $request->headers->set('X-Extension-Auth', 'extension-header');

        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'vault_delete_state' => '{"processId":"pid"}',
                'X-Extension-Auth' => 'extension-header',
            ])
            ->willReturn(new JsonResponse(['status' => 'ok']));

        $controller = new VaultDeleteController($this->createMock(LoggerInterface::class));
        $response = $controller->vaultDeleteState($forwardingService, $request);

        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }
}