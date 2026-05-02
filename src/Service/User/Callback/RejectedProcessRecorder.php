<?php

namespace App\Service\User\Callback;

use App\Entity\Process;
use App\Repository\ProcessRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RejectedProcessRecorder
{
    public function __construct(
        private ProcessRepository $processRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function markRejected(string $processId, string $reason): void
    {
        $process = $this->processRepository->findOneBy([
            'processId' => $processId,
        ]) ?? new Process();

        $process->setProcessId($processId);
        $process->setAuthId($reason);
        $process->setAllowed(false);

        $this->entityManager->persist($process);
        $this->entityManager->flush();

        $this->logger->info('Process marked as rejected', [
            'process_id' => $processId,
            'reason' => $reason,
        ]);
    }
}
