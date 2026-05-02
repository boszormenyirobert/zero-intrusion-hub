<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\InstanceSettings;
use App\Entity\Process;
use App\Entity\User;
use App\Entity\WhitelistedUsers;
use App\Repository\InstanceSettingsRepository;
use App\Repository\ProcessRepository;
use App\Repository\UserRepository;
use App\Repository\WhitelistedUsersRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Doctrine\Persistence\ManagerRegistry;

final class RepositoryCoverageTest extends TestCase
{
    public function testInstanceSettingsRepositoryCanBeConstructedWithRegistry(): void
    {
        $repository = new InstanceSettingsRepository($this->createMock(ManagerRegistry::class));

        self::assertInstanceOf(InstanceSettingsRepository::class, $repository);
    }

    public function testInstanceSettingsRepositoryFindCurrentSettingsUsesAscendingIdOrder(): void
    {
        $settings = new InstanceSettings();
        $repository = $this->getMockBuilder(InstanceSettingsRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repository->expects(self::once())
            ->method('findOneBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn($settings);

        self::assertSame($settings, $repository->findCurrentSettings());
    }

    public function testUserRepositoryFindOneByEmailAndPublicIdDelegatesToFindOneBy(): void
    {
        $user = new User();
        $repository = $this->getMockBuilder(UserRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repository->expects(self::once())
            ->method('findOneBy')
            ->with([
                'email' => 'user@example.com',
                'publicId' => 'public-1',
            ])
            ->willReturn($user);

        self::assertSame($user, $repository->findOneByEmailAndPublicId('user@example.com', 'public-1'));
    }

    public function testProcessRepositoryFindRejectedRegistrationProcessBuildsExpectedQuery(): void
    {
        $process = new Process();
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('getOneOrNullResult')->willReturn($process);

        $builder = $this->createMock(QueryBuilder::class);
        $builder->expects(self::exactly(2))->method('andWhere')->willReturnSelf();
        $builder->expects(self::exactly(2))
            ->method('setParameter')
            ->withConsecutive(
                ['processId', 'process-1'],
                ['reasons', ['registration_rejected_whitelist', 'registration_rejected_duplicate_user']]
            )
            ->willReturnSelf();
        $builder->expects(self::once())->method('setMaxResults')->with(1)->willReturnSelf();
        $builder->expects(self::once())->method('getQuery')->willReturn($query);

        $repository = $this->getMockBuilder(ProcessRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repository->expects(self::once())->method('createQueryBuilder')->with('p')->willReturn($builder);

        self::assertSame($process, $repository->findRejectedRegistrationProcess('process-1'));
    }

    public function testProcessRepositoryFindRejectedLoginProcessBuildsExpectedQuery(): void
    {
        $process = new Process();
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('getOneOrNullResult')->willReturn($process);

        $builder = $this->createMock(QueryBuilder::class);
        $builder->expects(self::exactly(2))->method('andWhere')->willReturnSelf();
        $builder->expects(self::exactly(2))
            ->method('setParameter')
            ->withConsecutive(
                ['processId', 'process-2'],
                ['reasons', ['login_rejected_whitelist']]
            )
            ->willReturnSelf();
        $builder->expects(self::once())->method('setMaxResults')->with(1)->willReturnSelf();
        $builder->expects(self::once())->method('getQuery')->willReturn($query);

        $repository = $this->getMockBuilder(ProcessRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repository->expects(self::once())->method('createQueryBuilder')->with('p')->willReturn($builder);

        self::assertSame($process, $repository->findRejectedLoginProcess('process-2'));
    }

    public function testWhitelistedUsersRepositoryFindActiveByEmailBuildsCaseInsensitiveActiveQuery(): void
    {
        $user = new WhitelistedUsers();
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('getOneOrNullResult')->willReturn($user);

        $builder = $this->createMock(QueryBuilder::class);
        $builder->expects(self::exactly(2))->method('andWhere')->willReturnSelf();
        $builder->expects(self::exactly(2))
            ->method('setParameter')
            ->withConsecutive(['email', 'USER@example.com'], ['active', true])
            ->willReturnSelf();
        $builder->expects(self::once())->method('setMaxResults')->with(1)->willReturnSelf();
        $builder->expects(self::once())->method('getQuery')->willReturn($query);

        $repository = $this->getMockBuilder(WhitelistedUsersRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repository->expects(self::once())->method('createQueryBuilder')->with('w')->willReturn($builder);

        self::assertSame($user, $repository->findActiveByEmail('USER@example.com'));
    }

    public function testWhitelistedUsersRepositoryFindOneByEmailTrimsInputBeforeQuerying(): void
    {
        $user = new WhitelistedUsers();
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('getOneOrNullResult')->willReturn($user);

        $builder = $this->createMock(QueryBuilder::class);
        $builder->expects(self::once())->method('andWhere')->with('LOWER(w.email) = LOWER(:email)')->willReturnSelf();
        $builder->expects(self::once())->method('setParameter')->with('email', 'user@example.com')->willReturnSelf();
        $builder->expects(self::once())->method('setMaxResults')->with(1)->willReturnSelf();
        $builder->expects(self::once())->method('getQuery')->willReturn($query);

        $repository = $this->getMockBuilder(WhitelistedUsersRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repository->expects(self::once())->method('createQueryBuilder')->with('w')->willReturn($builder);

        self::assertSame($user, $repository->findOneByEmail(' user@example.com '));
    }
}
