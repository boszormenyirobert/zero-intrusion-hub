<?php

namespace App\Service\Instance\HUB;

use App\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;

class SettingsFormHandler
{
    public function __construct(
        private InstanceSettingsRepository $instanceSettingsRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function handle(FormInterface $form): bool
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            return false;
        }

        $this->logger->info('Updating HUB instance registration state', [
            'route' => 'settings',
            'instance_registration' => $form->get('initialization')->getData(),
        ]);

        $instance = $this->instanceSettingsRepository->findCurrentSettings();

        if ($instance === null) {
            $this->logger->error('Instance settings not initialized.', [
                'route' => 'settings',
            ]);

            throw new LogicException('Instance settings not initialized.');
        }

        $instance->setInitialization(!($form->get('initialization')->getData()));

        $this->entityManager->persist($instance);
        $this->entityManager->flush();

        $this->logger->info('HUB instance registration state updated', [
            'route' => 'settings',
            'initialization' => $instance->isInitialization(),
        ]);

        return true;
    }
}