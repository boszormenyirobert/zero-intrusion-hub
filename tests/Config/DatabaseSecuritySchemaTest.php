<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\TestCase;

final class DatabaseSecuritySchemaTest extends TestCase
{
    public function testProcessTableDefinesUniqueConstraintForProcessId(): void
    {
        $contents = file_get_contents(__DIR__ . '/../../migrations/Version20260412183000.php');

        self::assertIsString($contents);
        self::assertStringContainsString('UNIQUE INDEX UNIQ_PROCESS_PROCESS_ID (process_id)', $contents);
    }

    public function testWhitelistedUsersTableDefinesUniqueConstraintForEmail(): void
    {
        $contents = file_get_contents(__DIR__ . '/../../migrations/Version20260412183000.php');

        self::assertIsString($contents);
        self::assertStringContainsString('UNIQUE INDEX UNIQ_WHITELISTED_USERS_EMAIL (email)', $contents);
    }

    public function testUserTableDefinesUniqueConstraintsForEmailAndPublicId(): void
    {
        $contents = file_get_contents(__DIR__ . '/../../migrations/Version20260414102000.php');

        self::assertIsString($contents);
        self::assertStringContainsString('UNIQUE INDEX UNIQ_USER_EMAIL (email)', $contents);
        self::assertStringContainsString('UNIQUE INDEX UNIQ_USER_PUBLIC_ID (public_id)', $contents);
    }
}