<?php

declare(strict_types=1);

namespace App\Tests\Attribute;

use App\Attribute\InitializationOnlyRoute;
use App\Attribute\InitializationOrJwtRoute;
use PHPUnit\Framework\TestCase;

final class RoutePolicyAttributesTest extends TestCase
{
    public function testInitializationOnlyRouteStoresReason(): void
    {
        $attribute = new InitializationOnlyRoute('Only during initialization');

        self::assertSame('Only during initialization', $attribute->reason);
    }

    public function testInitializationOrJwtRouteStoresReason(): void
    {
        $attribute = new InitializationOrJwtRoute('Initialization or valid JWT required');

        self::assertSame('Initialization or valid JWT required', $attribute->reason);
    }
}
