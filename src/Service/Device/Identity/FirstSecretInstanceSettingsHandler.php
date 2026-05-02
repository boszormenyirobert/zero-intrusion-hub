<?php

namespace App\Service\Device\Identity;

use App\Logger\LogTrace;
use App\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class FirstSecretInstanceSettingsHandler
{
    public function __construct(
        private InstanceSettingsRepository $instanceSettingsRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function handle(?string $registratorUserPublicId): void
    {
        $instanceSettings = $this->instanceSettingsRepository->findCurrentSettings();

        if (
            $instanceSettings === null
            || $instanceSettings->isInitialization() == false
            || !is_string($registratorUserPublicId)
            || $registratorUserPublicId === ''
        ) {
            return;
        }

        $instanceSettings->setPublicId($registratorUserPublicId);
        $this->entityManager->persist($instanceSettings);
        $this->entityManager->flush();

        $this->logger->info('Stored registrator publicId in instance settings', [
            'handler' => self::class,
            'instance_settings_id' => $instanceSettings->getId(),
            'registrator_public_id_hash' => LogTrace::fingerprint($registratorUserPublicId),
        ]);
    }
}
