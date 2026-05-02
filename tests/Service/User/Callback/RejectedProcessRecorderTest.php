<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Callback;

use App\Entity\Process;
use App\Repository\ProcessRepository;
use App\Service\User\Callback\RejectedProcessRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RejectedProcessRecorderTest extends TestCase
{
    public function testMarkRejectedUpdatesExistingProcessAndLogsReason(): void
    {
        $process = (new Process())->setProcessId('proc-1')->setAllowed(true);
        $repository = $this->createMock(ProcessRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['processId' => 'proc-1'])
            ->willReturn($process);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::identicalTo($process));
        $entityManager->expects(self::once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Process marked as rejected', [
                'process_id' => 'proc-1',
                'reason' => 'registration_rejected_whitelist',
            ]);

        $service = new RejectedProcessRecorder($repository, $entityManager, $logger);
        $service->markRejected('proc-1', 'registration_rejected_whitelist');

        self::assertSame('proc-1', $process->getProcessId());
        self::assertSame('registration_rejected_whitelist', $process->getAuthId());
        self::assertFalse($process->isAllowed());
    }

    public function testMarkRejectedCreatesNewProcessWhenRecordDoesNotExist(): void
    {
        $repository = $this->createMock(ProcessRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['processId' => 'proc-2'])
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (Process $process): bool {
                return $process->getProcessId() === 'proc-2'
                    && $process->getAuthId() === 'login_rejected_whitelist'
                    && $process->isAllowed() === false;
            }));
        $entityManager->expects(self::once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Process marked as rejected', [
                'process_id' => 'proc-2',
                'reason' => 'login_rejected_whitelist',
            ]);

        $service = new RejectedProcessRecorder($repository, $entityManager, $logger);
        $service->markRejected('proc-2', 'login_rejected_whitelist');
    }
}
