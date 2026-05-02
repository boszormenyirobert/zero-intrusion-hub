<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\DTO\AuthorizedCorporateIdentityDTO;
use App\DTO\CorporateDataDTO;
use App\Entity\OwnClient;
use App\Repository\OwnClientRepository;
use App\Service\Corporate\DatabaseService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class DatabaseServiceTest extends TestCase
{
    public function testCreateOwnClientPersistsAuthorizedIdentity(): void
    {
        $captured = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (OwnClient $client) use (&$captured): bool {
                $captured = $client;

                return true;
            }));
        $entityManager->expects(self::once())->method('flush');

        $service = new DatabaseService($this->createMock(OwnClientRepository::class), $entityManager);
        $service->createOwnClient(AuthorizedCorporateIdentityDTO::fromArray([
            'corporate_id' => 'corp-id',
            'corporate_id_key' => 'corp-key',
            'corporate_id_secret' => 'corp-secret',
            'ssl_public_key' => 'ssl-public-key',
        ]));

        self::assertInstanceOf(OwnClient::class, $captured);
        self::assertSame('corp-id', $captured->getCorporateId());
        self::assertSame('corp-key', $captured->getCorporateIdKey());
        self::assertSame('corp-secret', $captured->getCorporateIdSecret());
        self::assertSame('ssl-public-key', $captured->getSslPublicKey());
    }

    public function testUpdateOwnClientThrowsWhenNoRecordExists(): void
    {
        $repository = $this->createMock(OwnClientRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with([], ['id' => 'ASC'])->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $service = new DatabaseService($repository, $entityManager);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No OwnClient found to update.');
        $service->updateOwnClient(CorporateDataDTO::fromArray(['domain' => 'https://example.test']));
    }

    public function testUpdateOwnClientPersistsUpdatedDomain(): void
    {
        $client = new OwnClient();

        $repository = $this->createMock(OwnClientRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with([], ['id' => 'ASC'])->willReturn($client);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($client);
        $entityManager->expects(self::once())->method('flush');

        $service = new DatabaseService($repository, $entityManager);
        $service->updateOwnClient(CorporateDataDTO::fromArray(['domain' => 'https://example.test']));

        self::assertSame('https://example.test', $client->getDomain());
    }
}
