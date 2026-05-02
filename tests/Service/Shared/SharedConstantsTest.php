<?php

declare(strict_types=1);

namespace App\Tests\Service\Shared;

use App\Service\Shared\ProcessKey;
use App\Service\Shared\RoutePath;
use PHPUnit\Framework\TestCase;

final class SharedConstantsTest extends TestCase
{
    public function testProcessKeyDefinesExpectedConstantsAndPrivateConstructorIsInvokableViaReflection(): void
    {
        $reflection = new \ReflectionClass(ProcessKey::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $constructor = $reflection->getConstructor();

        self::assertTrue($constructor->isPrivate());
        self::assertSame('domain_read_qr_identity', ProcessKey::DOMAIN_READ_QR_IDENTITY);
        self::assertSame('restorePin', ProcessKey::RESTORE_PIN);
        self::assertSame('browserRegistrationVaultIdentity', ProcessKey::BROWSER_REGISTRATION_VAULT_IDENTITY);

        $constructor->setAccessible(true);
        $constructor->invoke($instance);

        self::assertInstanceOf(ProcessKey::class, $instance);
    }

    public function testRoutePathDefinesExpectedConstantsAndPrivateConstructorIsInvokableViaReflection(): void
    {
        $reflection = new \ReflectionClass(RoutePath::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $constructor = $reflection->getConstructor();

        self::assertTrue($constructor->isPrivate());
        self::assertSame('/qr-identity', RoutePath::QR_IDENTITY);
        self::assertSame('/replace/pin', RoutePath::REPLACE_PIN);
        self::assertSame('/registration/browser-identity', RoutePath::REGISTRATION_BROWSER_IDENTITY);

        $constructor->setAccessible(true);
        $constructor->invoke($instance);

        self::assertInstanceOf(RoutePath::class, $instance);
    }
}
