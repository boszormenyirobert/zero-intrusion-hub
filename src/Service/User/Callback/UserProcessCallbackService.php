<?php

namespace App\Service\User\Callback;

use App\DTO\RegistrationProcessDTO;
use App\Entity\User;
use App\Logger\LogTrace;
use App\Repository\UserRepository;
use App\Repository\WhitelistedUsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UserProcessCallbackService
{
    private const REASON_LOGIN_REJECTED_WHITELIST = 'login_rejected_whitelist';
    private const REASON_REGISTRATION_REJECTED_WHITELIST = 'registration_rejected_whitelist';
    private const REASON_REGISTRATION_REJECTED_DUPLICATE_USER = 'registration_rejected_duplicate_user';
    private const MESSAGE_LOGIN_REJECTED_WHITELIST = 'User login rejected because email is not whitelisted or inactive';
    private const MESSAGE_REGISTRATION_REJECTED_WHITELIST = 'User registration rejected because email is not whitelisted or inactive';
    private const MESSAGE_REGISTRATION_REJECTED_DUPLICATE = 'User registration rejected because email and publicId already exist';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private RegistrationSignatureValidator $registrationSignatureValidator,
        private RejectedProcessRecorder $rejectedProcessRecorder,
        private UserRepository $userRepository,
        private WhitelistedUsersRepository $whitelistedUsersRepository
    ) {
    }

    public function allowLoginProcess(RegistrationProcessDTO $authorizedUser): bool
    {
        if (!$this->verifySignature($authorizedUser)) {
            return false;
        }

        $registrationUser = $this->findUserByIdentity($authorizedUser);

        if (!$registrationUser) {
            $this->logger->info('User not found');

            return false;
        }

        if (!$this->isWhitelisted($authorizedUser)) {
            return $this->rejectLoginProcess($authorizedUser, $registrationUser);
        }

        return $this->allowWhitelistedLogin($authorizedUser, $registrationUser);
    }

    public function createRegisteredUser(RegistrationProcessDTO $process): bool
    {
        $this->logProcessInfo('Starting user creation from registration callback', $process);

        if (!$this->verifySignature($process)) {
            return false;
        }

        if (!$this->isWhitelisted($process)) {
            $this->rejectProcess($process, self::REASON_REGISTRATION_REJECTED_WHITELIST, self::MESSAGE_REGISTRATION_REJECTED_WHITELIST);
            return false;
        }

        $this->logProcessInfo('User registration allowed by whitelist', $process);

        $existingUser = $this->userRepository->findOneByEmailAndPublicId(
            $process->getEmail(),
            $process->getPublicId()
        );

        if ($existingUser !== null) {
            return $this->handleExistingRegisteredUser($process, $existingUser);
        }

        $user = $this->createAllowedUser($process);
        $this->saveUser($user);

        $this->logger->info('User created from registration callback', [
            'user_id' => $user->getId(),
            'email_hash' => LogTrace::fingerprint($user->getEmail()),
            'public_id_hash' => LogTrace::fingerprint($user->getPublicId()),
            'process_hash' => is_string($user->getProcess()) ? LogTrace::fingerprint($user->getProcess()) : null,
            'allowed' => $user->isAllowed(),
        ]);

        return true;
    }

    private function logVerificationError(): false
    {
        $this->logger->info('An error occurred during verification.');

        return false;
    }

    private function verifySignature(RegistrationProcessDTO $process): bool
    {
        if (!$this->registrationSignatureValidator->isValid($process)) {
            $this->logger->error('The signature is invalid.');

            return $this->logVerificationError();
        }

        $this->logger->info('The signature is valid.');

        return true;
    }

    private function findUserByIdentity(RegistrationProcessDTO $process): ?User
    {
        return $this->userRepository->findOneBy([
            'email' => $process->getEmail(),
            'publicId' => $process->getPublicId(),
        ]);
    }

    private function isWhitelisted(RegistrationProcessDTO $process): bool
    {
        return $this->whitelistedUsersRepository->findActiveByEmail($process->getEmail()) !== null;
    }

    private function rejectLoginProcess(RegistrationProcessDTO $process, User $user): bool
    {
        $this->rejectProcess($process, self::REASON_LOGIN_REJECTED_WHITELIST, self::MESSAGE_LOGIN_REJECTED_WHITELIST);

        $user->setAllowed(false);
        $user->setProcess(null);

        $this->saveUser($user);

        return false;
    }

    private function allowWhitelistedLogin(RegistrationProcessDTO $process, User $user): bool
    {
        $this->logProcessInfo('User login allowed by whitelist', $process);

        $user->setProcess($process->getProcessId());
        $user->setAllowed(true);

        $this->saveUser($user);

        return true;
    }

    private function handleExistingRegisteredUser(RegistrationProcessDTO $process, User $existingUser): bool
    {
        if ($existingUser->getProcess() === $process->getProcessId()) {
            $this->logger->info('Duplicate registration callback ignored because user already exists for process', [
                ...$this->createProcessLogContext($process),
                'existing_user_id' => $existingUser->getId(),
                'existing_user_allowed' => $existingUser->isAllowed(),
            ]);

            return true;
        }

        $this->rejectedProcessRecorder->markRejected($process->getProcessId(), self::REASON_REGISTRATION_REJECTED_DUPLICATE_USER);

        $this->logger->warning(self::MESSAGE_REGISTRATION_REJECTED_DUPLICATE, [
            ...$this->createProcessLogContext($process),
            'existing_user_id' => $existingUser->getId(),
        ]);

        return false;
    }

    private function createAllowedUser(RegistrationProcessDTO $process): User
    {
        $user = new User();
        $user->setEmail($process->getEmail());
        $user->setProcess($process->getProcessId());
        $user->setPublicId($process->getPublicId());
        $user->setAllowed(true);

        return $user;
    }

    private function rejectProcess(RegistrationProcessDTO $process, string $reason, string $message): void
    {
        $this->rejectedProcessRecorder->markRejected($process->getProcessId(), $reason);

        $this->logger->warning($message, $this->createProcessLogContext($process));
    }

    private function saveUser(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function logProcessInfo(string $message, RegistrationProcessDTO $process): void
    {
        $this->logger->info($message, $this->createProcessLogContext($process));
    }

    private function createProcessLogContext(RegistrationProcessDTO $process): array
    {
        return [
            'email_hash' => LogTrace::fingerprint($process->getEmail()),
            'public_id_hash' => LogTrace::fingerprint($process->getPublicId()),
            'process_hash' => LogTrace::fingerprint($process->getProcessId()),
        ];
    }
}
