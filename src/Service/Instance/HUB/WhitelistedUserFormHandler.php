<?php

namespace App\Service\Instance\HUB;

use App\Entity\WhitelistedUsers;
use App\Repository\WhitelistedUsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

class WhitelistedUserFormHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WhitelistedUsersRepository $whitelistedUsersRepository,
        private LoggerInterface $logger
    ) {}

    public function getAll(): array
    {
        return $this->whitelistedUsersRepository->findBy([], ['id' => 'ASC']);
    }

    public function handle(FormInterface $form): bool
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            return false;
        }

        $this->logger->info('Updating HUB instance registration state', [
            'route' => 'access',
            'white_listed_user' => $form->getData(),
        ]);

        $email = trim((string) $form->get('email')->getData());

        if ($this->whitelistedUsersRepository->findOneByEmail($email) !== null) {
            $form->get('email')->addError(new FormError('This email address is already in the whitelist.'));

            $this->logger->warning('Whitelisted HUB user creation rejected because email already exists', [
                'route' => 'access',
                'email' => $email,
            ]);

            return false;
        }

        $user = new WhitelistedUsers();
        $user->setEmail($email);
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

    public function updateStatus(int $id, bool $active): bool
    {
        $user = $this->whitelistedUsersRepository->find($id);

        if ($user === null) {
            $this->logger->warning('Whitelisted HUB user status update skipped because user was not found', [
                'route' => 'access_user_status',
                'whitelisted_user_id' => $id,
            ]);

            return false;
        }

        $user->setActive($active);
        $this->entityManager->flush();

        $this->logger->info('Whitelisted HUB user status updated', [
            'route' => 'access_user_status',
            'whitelisted_user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'active' => $user->isActive(),
        ]);

        return true;
    }

    public function delete(int $id): bool
    {
        $user = $this->whitelistedUsersRepository->find($id);

        if ($user === null) {
            $this->logger->warning('Whitelisted HUB user deletion skipped because user was not found', [
                'route' => 'access_user_delete',
                'whitelisted_user_id' => $id,
            ]);

            return false;
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->logger->info('Whitelisted HUB user deleted', [
            'route' => 'access_user_delete',
            'whitelisted_user_id' => $id,
            'email' => $user->getEmail(),
        ]);

        return true;
    }
}