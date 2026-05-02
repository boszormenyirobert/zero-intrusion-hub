<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SecurityConfigTest extends TestCase
{
    public function testLoginCallbackPathIsNotBoundToJsonLogin(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        $jsonLoginCheckPath = $config['security']['firewalls']['api']['json_login']['check_path'] ?? null;

        self::assertNotSame('/api/user-login/callback', $jsonLoginCheckPath);
    }

    public function testLegacyLoginCallbackJwtFirewallIsAbsent(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        self::assertArrayNotHasKey('api', $config['security']['firewalls'] ?? []);
    }

    public function testLegacyPublicAccessEntriesAreAbsentForRoutePolicyControlledEndpoints(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        $accessControl = $config['security']['access_control'] ?? [];
        $paths = array_map(
            static fn (array $rule): string => (string) ($rule['path'] ?? ''),
            is_array($accessControl) ? $accessControl : []
        );

        self::assertNotContains('^/api/registration/callback', $paths);
        self::assertNotContains('^/api/user-login/callback', $paths);
        self::assertNotContains('^/api/nfc/users', $paths);
    }

    public function testLegacyMainFirewallIsAbsent(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        self::assertArrayNotHasKey('main', $config['security']['firewalls'] ?? []);
    }

    public function testLegacyInMemoryProviderIsAbsent(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        self::assertArrayNotHasKey('users_in_memory', $config['security']['providers'] ?? []);
    }

    public function testLegacyLoginSuccessHandlerFileIsAbsent(): void
    {
        self::assertFileDoesNotExist(__DIR__ . '/../../src/Security/LoginSuccessHandler.php');
    }

    public function testLegacyLoginFailureHandlerFileIsAbsent(): void
    {
        self::assertFileDoesNotExist(__DIR__ . '/../../src/Security/LoginFailureHandler.php');
    }
}