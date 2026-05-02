<?php

declare(strict_types=1);

namespace App\Tests\Service\Instance\HUB;

use App\DTO\WhitelistedUserInputDTO;
use App\Entity\WhitelistedUsers;
use App\Logger\LogTrace;
use App\Repository\WhitelistedUsersRepository;
use App\Service\Instance\HUB\WhitelistedUserFormHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

final class WhitelistedUserFormHandlerTest extends TestCase
{
    public function testHandleLogsHashedEmailWhenCreatingUser(): void
    {
        $formData = new WhitelistedUserInputDTO();
        $formData->email = ' user@example.test ';
        $formData->active = true;

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($formData);

        $repository = $this->createMock(WhitelistedUsersRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneByEmail')
            ->with('user@example.test')
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (WhitelistedUsers $user): bool {
                return $user->getEmail() === 'user@example.test' && $user->isActive() === true;
            }));
        $entityManager->expects(self::once())->method('flush');

        $logger = $this->createSpyLogger();

        $handler = new WhitelistedUserFormHandler($entityManager, $repository, $logger);

        self::assertTrue($handler->handle($form));
        self::assertCount(2, $logger->records);
        self::assertSame('Updating HUB instance registration state', $logger->records[0]['message']);
        self::assertSame([
            'route' => 'access',
            'email_hash' => LogTrace::fingerprint(' user@example.test '),
            'email_present' => true,
            'active' => true,
        ], $logger->records[0]['context']);
        self::assertArrayNotHasKey('email', $logger->records[0]['context']);
        self::assertArrayNotHasKey('white_listed_user', $logger->records[0]['context']);
        self::assertSame('Whitelisted HUB user created', $logger->records[1]['message']);
        self::assertSame([
            'route' => 'access',
            'email_hash' => LogTrace::fingerprint('user@example.test'),
            'active' => true,
        ], $logger->records[1]['context']);
        self::assertArrayNotHasKey('email', $logger->records[1]['context']);
    }

    public function testHandleReturnsFalseWhenFormIsNotSubmitted(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('isSubmitted')->willReturn(false);
        $form->expects(self::never())->method('isValid');

        $repository = $this->createMock(WhitelistedUsersRepository::class);
        $repository->expects(self::never())->method('findOneByEmail');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $handler = new WhitelistedUserFormHandler($entityManager, $repository, $this->createSpyLogger());

        self::assertFalse($handler->handle($form));
    }

    public function testHandleLogsHashedEmailWhenDuplicateExists(): void
    {
        $formData = new WhitelistedUserInputDTO();
        $formData->email = 'user@example.test';
        $formData->active = true;

        $emailField = $this->createMock(FormInterface::class);
        $emailField
            ->expects(self::once())
            ->method('addError')
            ->with(self::callback(static function (FormError $error): bool {
                return $error->getMessage() === 'This email address is already in the whitelist.';
            }));

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($formData);
        $form->method('get')->with('email')->willReturn($emailField);

        $existingUser = (new WhitelistedUsers())
            ->setEmail('user@example.test')
            ->setActive(true);

        $repository = $this->createMock(WhitelistedUsersRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneByEmail')
            ->with('user@example.test')
            ->willReturn($existingUser);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $logger = $this->createSpyLogger();

        $handler = new WhitelistedUserFormHandler($entityManager, $repository, $logger);

        self::assertFalse($handler->handle($form));
        self::assertCount(2, $logger->records);
        self::assertSame('warning', $logger->records[1]['level']);
        self::assertSame('Whitelisted HUB user creation rejected because email already exists', $logger->records[1]['message']);
        self::assertSame([
            'route' => 'access',
            'email_hash' => LogTrace::fingerprint('user@example.test'),
        ], $logger->records[1]['context']);
        self::assertArrayNotHasKey('email', $logger->records[1]['context']);
    }

    public function testUpdateStatusLogsHashedEmail(): void
    {
        $user = (new WhitelistedUsers())
            ->setEmail('user@example.test')
            ->setActive(false);
        $this->setEntityId($user, 12);

        $repository = $this->createMock(WhitelistedUsersRepository::class);
        $repository
            ->expects(self::once())
            ->method('find')
            ->with(12)
            ->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $logger = $this->createSpyLogger();

        $handler = new WhitelistedUserFormHandler($entityManager, $repository, $logger);

        self::assertTrue($handler->updateStatus(12, true));
        self::assertCount(1, $logger->records);
        self::assertSame('Whitelisted HUB user status updated', $logger->records[0]['message']);
        self::assertSame([
            'route' => 'access_user_status',
            'whitelisted_user_id' => 12,
            'email_hash' => LogTrace::fingerprint('user@example.test'),
            'active' => true,
        ], $logger->records[0]['context']);
        self::assertArrayNotHasKey('email', $logger->records[0]['context']);
    }

    public function testUpdateStatusReturnsFalseWhenUserIsMissing(): void
    {
        $repository = $this->createMock(WhitelistedUsersRepository::class);
        $repository->expects(self::once())->method('find')->with(12)->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $handler = new WhitelistedUserFormHandler($entityManager, $repository, $this->createSpyLogger());

        self::assertFalse($handler->updateStatus(12, true));
    }

    public function testDeleteLogsHashedEmail(): void
    {
        $user = (new WhitelistedUsers())
            ->setEmail('user@example.test')
            ->setActive(true);
        $this->setEntityId($user, 24);

        $repository = $this->createMock(WhitelistedUsersRepository::class);
        $repository
            ->expects(self::once())
            ->method('find')
            ->with(24)
            ->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($user);
        $entityManager->expects(self::once())->method('flush');

        $logger = $this->createSpyLogger();

        $handler = new WhitelistedUserFormHandler($entityManager, $repository, $logger);

        self::assertTrue($handler->delete(24));
        self::assertCount(1, $logger->records);
        self::assertSame('Whitelisted HUB user deleted', $logger->records[0]['message']);
        self::assertSame([
            'route' => 'access_user_delete',
            'whitelisted_user_id' => 24,
            'email_hash' => LogTrace::fingerprint('user@example.test'),
        ], $logger->records[0]['context']);
        self::assertArrayNotHasKey('email', $logger->records[0]['context']);
    }

    public function testDeleteReturnsFalseWhenUserIsMissing(): void
    {
        $repository = $this->createMock(WhitelistedUsersRepository::class);
        $repository->expects(self::once())->method('find')->with(24)->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');

        $handler = new WhitelistedUserFormHandler($entityManager, $repository, $this->createSpyLogger());

        self::assertFalse($handler->delete(24));
    }

    private function createSpyLogger(): AbstractLogger
    {
        return new class () extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }

    private function setEntityId(WhitelistedUsers $user, int $id): void
    {
        $reflection = new \ReflectionProperty($user, 'id');
        $reflection->setValue($user, $id);
    }
}
