<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\InstanceSettings;
use App\Entity\OwnClient;
use App\Entity\Process;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class EntityCoverageTest extends TestCase
{
    public function testInstanceSettingsAccessorsWork(): void
    {
        $entity = new InstanceSettings();

        self::assertNull($entity->getId());
        self::assertNull($entity->isInitialization());
        self::assertNull($entity->getPublicId());
        self::assertSame($entity, $entity->setInitialization(true));
        self::assertSame($entity, $entity->setPublicId('public-id'));
        self::assertTrue((bool) $entity->isInitialization());
        self::assertSame('public-id', $entity->getPublicId());
    }

    public function testProcessAccessorsWork(): void
    {
        $entity = new Process();

        self::assertNull($entity->getId());
        self::assertSame($entity, $entity->setProcessId('process-id'));
        self::assertSame($entity, $entity->setAuthId('auth-id'));
        self::assertSame($entity, $entity->setAllowed(true));
        self::assertSame('process-id', $entity->getProcessId());
        self::assertSame('auth-id', $entity->getAuthId());
        self::assertTrue((bool) $entity->isAllowed());
    }

    public function testUserAccessorsAndSecurityContractWork(): void
    {
        $user = new User();

        self::assertNull($user->getId());
        self::assertSame($user, $user->setProcess('process-id'));
        self::assertSame($user, $user->setEmail('user@example.test'));
        self::assertSame($user, $user->setPassword('secret'));
        self::assertSame($user, $user->setAllowed(true));
        self::assertSame($user, $user->setPublicId('public-id'));
        $user->eraseCredentials();

        self::assertSame('process-id', $user->getProcess());
        self::assertSame('user@example.test', $user->getEmail());
        self::assertSame('secret', $user->getPassword());
        self::assertTrue((bool) $user->isAllowed());
        self::assertSame('public-id', $user->getPublicId());
        self::assertSame('user@example.test', $user->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testOwnClientAccessorsWork(): void
    {
        $entity = new OwnClient();

        self::assertNull($entity->getId());
        self::assertSame($entity, $entity->setCorporateId('corporate-id'));
        self::assertSame($entity, $entity->setCorporateIdKey('corporate-key'));
        self::assertSame($entity, $entity->setCorporateIdSecret('corporate-secret'));
        self::assertSame($entity, $entity->setSslPublicKey('ssl-public-key'));
        self::assertSame($entity, $entity->setDomain('https://example.test'));
        self::assertSame('corporate-id', $entity->getCorporateId());
        self::assertSame('corporate-key', $entity->getCorporateIdKey());
        self::assertSame('corporate-secret', $entity->getCorporateIdSecret());
        self::assertSame('ssl-public-key', $entity->getSslPublicKey());
        self::assertSame('https://example.test', $entity->getDomain());
    }
}
