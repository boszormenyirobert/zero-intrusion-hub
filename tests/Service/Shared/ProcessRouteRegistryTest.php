<?php

declare(strict_types=1);

namespace App\Tests\Service\Shared;

use App\Service\Shared\ProcessKey;
use App\Service\Shared\ProcessRouteRegistry;
use App\Service\Shared\RoutePath;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ProcessRouteRegistryTest extends TestCase
{
    public function testResolveReturnsConfiguredRoute(): void
    {
        $registry = new ProcessRouteRegistry($this->createParameterBag());

        $route = $registry->resolve(ProcessKey::DOMAIN_READ_QR_IDENTITY);

        self::assertNotNull($route);
        self::assertSame('/domain-read', $route->basePath);
        self::assertSame(RoutePath::QR_IDENTITY, $route->endpointPath);
    }

    public function testResolveReturnsStaticCorporateRoute(): void
    {
        $registry = new ProcessRouteRegistry($this->createParameterBag());

        $route = $registry->resolve(ProcessKey::BUSINESS_CREATE);

        self::assertNotNull($route);
        self::assertSame('/api/registration/corporate', $route->basePath);
        self::assertSame(RoutePath::BUSINESS_CREATE, $route->endpointPath);
    }

    private function createParameterBag(): ParameterBagInterface
    {
        $values = [
            'ZERO_INTRUSION_ONE_TOUCH' => '/one-touch',
            'ZERO_INTRUSION_DOMAIN_READ_BASE' => '/domain-read',
            'ZERO_INTRUSION_DOMAIN_DELETE_BASE' => '/domain-delete',
            'ZERO_INTRUSION_SHARED_BASE' => '/shared',
            'ZERO_INTRUSION_VAULT_READ_BASE' => '/vault-read',
            'ZERO_INTRUSION_VAULT_EDIT_BASE' => '/vault-edit',
            'ZERO_INTRUSION_VAULT_DELETE_BASE' => '/vault-delete',
            'ZERO_INTRUSION_SYSTEM_HUB_BASE' => '/system-hub',
            'ZERO_INTRUSION_ACCOUNT' => '/account',
        ];

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag
            ->method('get')
            ->willReturnCallback(static fn (string $name): string => $values[$name]);

        return $parameterBag;
    }
}
