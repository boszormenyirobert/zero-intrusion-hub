<?php

namespace App\Service\Instance\HUB;

use App\Entity\WhitelistedUsers;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;

class WhitelistedUserFormHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function handle(FormInterface $form): bool
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            return false;
        }

        $this->logger->info('Updating HUB instance registration state', [
            'route' => 'access',
            'white_listed_user' => $form->getData(),
        ]);

        $user = new WhitelistedUsers();
        $user->setEmail($form->get('email')->getData());
        $user->setActive($form->get('active')->getData());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->logger->info('Whitelisted HUB user created', [
            'route' => 'access',
            'email' => $user->getEmail(),
            'active' => $user->isActive(),
        ]);

        return true;
    }
}