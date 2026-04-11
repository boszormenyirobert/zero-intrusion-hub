<?php

namespace App\Service\Instance\HUB;

use App\Entity\InstanceSettings;
use App\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class InstanceRegistrationService
{
    public function __construct(
        private LoggerInterface $logger,
        private InstanceSettingsRepository $instanceSettingsRepository,
        private EntityManagerInterface $entityManager
    ) {
        $this->initializeInstanceSettings();
    }

    private function initializeInstanceSettings(): bool
    {
        $instance = $this->instanceSettingsRepository->findCurrentSettings();

        if (empty($instance)) {
            $this->logger->info('No instance settings found, creating new one with initialization set to true');
            $instanceSettings = new InstanceSettings();
            $instanceSettings->setInitialization(true);
            $this->entityManager->persist($instanceSettings);
            $this->entityManager->flush();

            return true;
        }

        $this->logger->info('InstanceRegistrationService getInstanceRegistrationState called', ['value' => $instance->isInitialization()]);

        return false;
    }

    public function getInitializationState(): bool
    {
        $instance = $this->instanceSettingsRepository->findCurrentSettings();

        if (empty($instance)) {
            $this->logger->warning('No instance settings found, returning false as initialization state');

            return false;
        }

        return $instance->isInitialization();
    }
}