<?php

namespace App\Controller\User;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;
use App\Service\Corporate\AuthorizationControllService;
use App\Entity\User;
use App\Entity\Process;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Instance\InstanceSettingsService;
use App\Repository\ProcessRepository;
use App\Repository\UserRepository;
use App\DTO\RegistrationProcessDTO;
use App\Repository\OwnClientRepository;
use \Symfony\Component\HttpFoundation\JsonResponse;

class UserService
{
        public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private ContainerBagInterface $params,
        private AuthorizationControllService $authorizationControllService,
        private InstanceSettingsService $instanceSettingsService,
        private ProcessRepository $processRepository,
        private UserRepository $userRepository,
        private OwnClientRepository $ownClientRepository
    ) {}

    /**
     * Requests a QR code for a user registration or authentication process.
     *
     * @param string $process
     * @param array $corporateIdentification
     * @param string|null $userPublicId
     * @return array
     */
    public function getQrCode(string $process, array $corporateIdentification = [], ?string $userPublicId = null): array
    {
        $response = $this->prepareSecurePostRequest($process, $corporateIdentification, $userPublicId);
        $this->logger->critical('response data', ['response' => $response]);

        $authorizedData = $this->authorizationControllService->controllAuthorization($response);        
        $this->logger->critical('Authorized data', ['authorizedData' => $authorizedData]);
        $this->saveProcess($process, $authorizedData);
        
        return $authorizedData;
    }

    /**
     * Requests NFC user data for a process.
     *
     * @param string $process
     * @param array $corporateIdentification
     * @param string|null $userPublicId
     * @return mixed
     */
    public function getNfcUsers(string $process, array $corporateIdentification = [], ?string $userPublicId = null): JsonResponse
    {
        return $this->prepareSecurePostRequest($process, $corporateIdentification, $userPublicId);
    }

    private function prepareSecurePostRequest(string $process, array $corporateIdentification = [], ?string $userPublicId = null): JsonResponse {
        [
            'publicId' => $publicId,
            'domain'   => $domain,
            'hmac'     => $hmac,
        ] = $this->getPublicIdDomainHmac($corporateIdentification);
        
        $payload = $this->getRequestPayload($publicId, $hmac, $domain, $userPublicId);
        
        return $this->authorizationControllService->getSecurePostRequest([
            $process => $payload]);     
    }

    /**
     * Allows a user to proceed with login if their registration process is valid and verified.
     *
     * @param RegistrationProcessDTO $authorizedUser
     * @return bool
     */
    public function allowSetUserLoginProcess(RegistrationProcessDTO $authorizedUser): bool
    {
        if ($this->sslValidation($authorizedUser) !== 1) {
            return $this->logVerificationError();
        }

        $this->logger->critical("The signature is valid.");

        $registrationUser = $this->userRepository->findOneBy([
            'email' => $authorizedUser->getEmail(),
            'publicId' => $authorizedUser->getPublicId()
        ]);

        if (!$registrationUser) {
            $this->logger->critical('User not found');
            return false;
        }

        $registrationUser->setProcess($authorizedUser->getProcessId());
        $registrationUser->setAllowed(true);

        $this->entityManager->persist($registrationUser);
        $this->entityManager->flush();

        return true;        
    }

    /**
     * Creates a new user entity if the registration process is valid and verified.
     *
     * @param RegistrationProcessDTO $process
     * @return bool
     */
    public function createUser(RegistrationProcessDTO $process): bool
    {
        if ($this->sslValidation($process) !== 1) {
            return $this->logVerificationError();
        }

        $user = new User();
        $user->setEmail($process->getEmail());
        $user->setProcess($process->getProcessId());
        $user->setPublicId($process->getPublicId());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Saves a process entity for tracking registration or domain processes.
     *
     * @param string $process
     * @param array $authorizedData
     * @return void
     */
    private function saveProcess(string $process, array $authorizedData): void {
        $saveProcess = new Process();
        $processId = $process == 'user_registration'  ? 'registrationProcessId' : 'domainProcessId';
        $idValue = $authorizedData[$processId] ?? null;
        if ($idValue === null) {
            $this->logger->error("Missing process id key '$processId' in authorizedData", ['authorizedData' => $authorizedData]);
            return;
        }
        $saveProcess->setProcessId($idValue);
        $saveProcess->setAllowed(false);

        $this->entityManager->persist($saveProcess);
        $this->entityManager->flush();
    }

    /**
     * Builds the request payload for secure backend communication.
     *
     * @param string $publicId
     * @param string $registrationIdentity
     * @param string $domain
     * @param string|null $userPublicId
     * @return array
     */
    private function getRequestPayload(string $publicId, string $registrationIdentity, string $domain, ?string $userPublicId = null): array {
        return [
            'corporatePublicId' => $publicId,
            'corporateAuthentication' => $registrationIdentity,
            'domain' => $domain,
            'userPublicId' => $userPublicId
        ];
    }

    /**
     * Returns the publicId, domain, and HMAC for a request.
     *
     * @param array $corporateIdentification
     * @return array
     */
    private function getPublicIdDomainHmac(array $corporateIdentification = []): array
    {
        if (empty($corporateIdentification)) {
            return [
                'publicId' => $this->instanceSettingsService->getInstancePublicId(),
                'domain'   => $this->instanceSettingsService->getInstanceDomain(),
                'hmac'     => $this->authorizationControllService->generateRequestIdentity(),
            ];
        }

        return [
            'publicId' => $corporateIdentification['publicId'] ?? null,
            'domain'   => $corporateIdentification['domain'] ?? null,
            'hmac'     => $corporateIdentification['hmac'] ?? null,
        ];
    }
    
    /**
     * Validates the SSL signature of a registration process using the corporate public key.
     *
     * @param RegistrationProcessDTO $process
     * @return int|false
     */
    private function sslValidation(RegistrationProcessDTO $process): int|false
    {
        $recivedSignature = base64_decode($process->getSignature());

            $userIdentity = json_encode([                    
                'publicId' => $process->getPublicId(),
                'email' => $process->getEmail()           
            ]);                      

        $ownClient = $this->ownClientRepository->findOneBy([], ['id' => 'ASC']); 

        $publicKeyPem =  $ownClient->getSslPublicKey();
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        
        if (!$publicKey) {
            $this->logger->critical('Failed to load public key: ' . openssl_error_string());
        }

        return openssl_verify($userIdentity, $recivedSignature, $publicKey, OPENSSL_ALGO_SHA256);
    }

    /**
     * Logs signature verification errors and always returns false.
     *
     * @return false
     */
    private function logVerificationError(): false
    {
        $this->logger->critical("An error occurred during verification.");        
        return false;
    }
}