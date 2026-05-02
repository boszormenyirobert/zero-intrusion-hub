<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Callback;

use App\DTO\RegistrationProcessDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\WhitelistedUsersRepository;
use App\Service\User\Callback\RegistrationSignatureValidator;
use App\Service\User\Callback\RejectedProcessRecorder;
use App\Service\User\Callback\UserProcessCallbackService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class UserProcessCallbackServiceTest extends TestCase
{
    public function testAllowLoginProcessReturnsFalseWhenSignatureInvalid(): void
    {
        $validator = $this->createMock(RegistrationSignatureValidator::class);
        $validator->expects(self::once())->method('isValid')->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = $this->createService(
            entityManager: $entityManager,
            validator: $validator
        );

        self::assertFalse($service->allowLoginProcess($this->createProcessDto()));
    }

    public function testAllowLoginProcessRejectsNonWhitelistedUser(): void
    {
        $user = (new User())
            ->setEmail('user@example.test')
            ->setPublicId('public-id')
            ->setProcess('old-process')
            ->setAllowed(true);

        $validator = $this->createMock(RegistrationSignatureValidator::class);
        $validator->method('isValid')->willReturn(true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'user@example.test', 'publicId' => 'public-id'])
            ->willReturn($user);

        $whitelistRepository = $this->createMock(WhitelistedUsersRepository::class);
        $whitelistRepository
            ->expects(self::once())
            ->method('findActiveByEmail')
            ->with('user@example.test')
            ->willReturn(null);

        $rejectedRecorder = $this->createMock(RejectedProcessRecorder::class);
        $rejectedRecorder
            ->expects(self::once())
            ->method('markRejected')
            ->with('process-123', 'login_rejected_whitelist');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($user);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(
            entityManager: $entityManager,
            validator: $validator,
            rejectedRecorder: $rejectedRecorder,
            userRepository: $userRepository,
            whitelistedUsersRepository: $whitelistRepository
        );

        self::assertFalse($service->allowLoginProcess($this->createProcessDto()));
        self::assertFalse((bool) $user->isAllowed());
        self::assertNull($user->getProcess());
    }

    public function testAllowLoginProcessAllowsWhitelistedUser(): void
    {
        $user = (new User())
            ->setEmail('user@example.test')
            ->setPublicId('public-id')
            ->setAllowed(false);

        $validator = $this->createMock(RegistrationSignatureValidator::class);
        $validator->method('isValid')->willReturn(true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $whitelistRepository = $this->createMock(WhitelistedUsersRepository::class);
        $whitelistRepository->method('findActiveByEmail')->willReturn($this->createMock(\App\Entity\WhitelistedUsers::class));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($user);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(
            entityManager: $entityManager,
            validator: $validator,
            userRepository: $userRepository,
            whitelistedUsersRepository: $whitelistRepository
        );

        self::assertTrue($service->allowLoginProcess($this->createProcessDto()));
        self::assertTrue((bool) $user->isAllowed());
        self::assertSame('process-123', $user->getProcess());
    }

    public function testAllowLoginProcessReturnsFalseWhenUserNotFound(): void
    {
        $validator = $this->createMock(RegistrationSignatureValidator::class);
        $validator->method('isValid')->willReturn(true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'user@example.test', 'publicId' => 'public-id'])
            ->willReturn(null);

        $whitelistRepository = $this->createMock(WhitelistedUsersRepository::class);
        $whitelistRepository->expects(self::never())->method('findActiveByEmail');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = $this->createService(
            entityManager: $entityManager,
            validator: $validator,
            userRepository: $userRepository,
            whitelistedUsersRepository: $whitelistRepository
        );

        self::assertFalse($service->allowLoginProcess($this->createProcessDto()));
    }

    public function testCreateRegisteredUserReturnsFalseWhenSignatureInvalid(): void
    {
        $validator = $this->createMock(RegistrationSignatureValidator::class);
        $validator->expects(self::once())->method('isValid')->willReturn(false);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::never())->method('findOneByEmailAndPublicId');

        $whitelistRepository = $this->createMock(WhitelistedUsersRepository::class);
        $whitelistRepository->expects(self::never())->method('findActiveByEmail');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = $this->createService(
            entityManager: $entityManager,
            validator: $validator,
            userRepository: $userRepository,
            whitelistedUsersRepository: $whitelistRepository
        );

        self::assertFalse($service->createRegisteredUser($this->createProcessDto()));
    }

    public function testCreateRegisteredUserRejectsNonWhitelistedUser(): void
    {
        $validator = $this->createMock(RegistrationSignatureValidator::class);
        $validator->method('isValid')->willReturn(true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::never())->method('findOneByEmailAndPublicId');

        $whitelistRepository = $this->createMock(WhitelistedUsersRepository::class);
        $whitelistRepository
            ->expects(self::once())
            ->method('findActiveByEmail')
            ->with('user@example.test')
            ->willReturn(null);

        $rejectedRecorder = $this->createMock(RejectedProcessRecorder::class);
        $rejectedRecorder
            ->expects(self::once())
            ->method('markRejected')
            ->with('process-123', 'registration_rejected_whitelist');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = $this->createService(
            entityManager: $entityManager,
            validator: $validator,
            rejectedRecorder: $rejectedRecorder,
            userRepository: $userRepository,
            whitelistedUsersRepository: $whitelistRepository
        );

        self::assertFalse($service->createRegisteredUser($this->createProcessDto()));
    }

    public function testCreateRegisteredUserReturnsTrueForDuplicateProcess(): void
    {
        $existingUser = (new User())
            ->setEmail('user@example.test')
            ->setPublicId('public-id')
            ->setProcess('process-123')
            ->setAllowed(true);

        $validator = $this->createMock(RegistrationSignatureValidator::class);
        $validator->method('isValid')->willReturn(true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneByEmailAndPublicId')
            ->with('user@example.test', 'public-id')
            ->willReturn($existingUser);

        $whitelistRepository = $this->createMock(WhitelistedUsersRepository::class);
        $whitelistRepository->method('findActiveByEmail')->willReturn($this->createMock(\App\Entity\WhitelistedUsers::class));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = $this->createService(
            entityManager: $entityManager,
            validator: $validator,
            userRepository: $userRepository,
            whitelistedUsersRepository: $whitelistRepository
        );

        self::assertTrue($service->createRegisteredUser($this->createProcessDto()));
    }

    public function testCreateRegisteredUserRejectsDuplicateIdentityWithDifferentProcess(): void
    {
        $existingUser = (new User())
            ->setEmail('user@example.test')
            ->setPublicId('public-id')
            ->setProcess('different-process');

        $validator = $this->createMock(RegistrationSignatureValidator::class);
        $validator->method('isValid')->willReturn(true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneByEmailAndPublicId')->willReturn($existingUser);

        $whitelistRepository = $this->createMock(WhitelistedUsersRepository::class);
        $whitelistRepository->method('findActiveByEmail')->willReturn($this->createMock(\App\Entity\WhitelistedUsers::class));

        $rejectedRecorder = $this->createMock(RejectedProcessRecorder::class);
        $rejectedRecorder
            ->expects(self::once())
            ->method('markRejected')
            ->with('process-123', 'registration_rejected_duplicate_user');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = $this->createService(
            entityManager: $entityManager,
            validator: $validator,
            rejectedRecorder: $rejectedRecorder,
            userRepository: $userRepository,
            whitelistedUsersRepository: $whitelistRepository
        );

        self::assertFalse($service->createRegisteredUser($this->createProcessDto()));
    }

    public function testCreateRegisteredUserPersistsNewUser(): void
    {
        $validator = $this->createMock(RegistrationSignatureValidator::class);
        $validator->method('isValid')->willReturn(true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneByEmailAndPublicId')->willReturn(null);

        $whitelistRepository = $this->createMock(WhitelistedUsersRepository::class);
        $whitelistRepository->method('findActiveByEmail')->willReturn($this->createMock(\App\Entity\WhitelistedUsers::class));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (mixed $user): bool {
                return $user instanceof User
                    && $user->getEmail() === 'user@example.test'
                    && $user->getPublicId() === 'public-id'
                    && $user->getProcess() === 'process-123'
                    && $user->isAllowed() === true;
            }));
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(
            entityManager: $entityManager,
            validator: $validator,
            userRepository: $userRepository,
            whitelistedUsersRepository: $whitelistRepository
        );

        self::assertTrue($service->createRegisteredUser($this->createProcessDto()));
    }

    private function createService(
        ?EntityManagerInterface $entityManager = null,
        ?LoggerInterface $logger = null,
        ?RegistrationSignatureValidator $validator = null,
        ?RejectedProcessRecorder $rejectedRecorder = null,
        ?UserRepository $userRepository = null,
        ?WhitelistedUsersRepository $whitelistedUsersRepository = null
    ): UserProcessCallbackService {
        return new UserProcessCallbackService(
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $logger ?? $this->createMock(LoggerInterface::class),
            $validator ?? $this->createMock(RegistrationSignatureValidator::class),
            $rejectedRecorder ?? $this->createMock(RejectedProcessRecorder::class),
            $userRepository ?? $this->createMock(UserRepository::class),
            $whitelistedUsersRepository ?? $this->createMock(WhitelistedUsersRepository::class)
        );
    }

    private function createProcessDto(): RegistrationProcessDTO
    {
        return RegistrationProcessDTO::mapFromArrayLogin([
            'signature' => 'signature',
            'publicId' => 'public-id',
            'email' => 'user@example.test',
            'processId' => 'process-123',
        ]);
    }
}
