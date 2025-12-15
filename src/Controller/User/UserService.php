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

    public function getQrCode($process, $corporateIdentification, $userPublicId = null)
    {
        [
            'publicId' => $publicId,
            'domain'   => $domain,
            'hmac'     => $hmac,
        ] = $this->getPublicIdDomainHmac($corporateIdentification);

        $response = $this->authorizationControllService->getSecurePostRequest([
            $process => $this->getRequestPayload($publicId, $hmac, $domain, $userPublicId),
        ]);
        
        $authorizedData = $this->authorizationControllService->controllAuthorization($response);
        $this->saveProcess($process, $authorizedData);

        return $authorizedData;
    }

    public function getNfcUsers($process, $corporateIdentification, $userPublicId = null)
    {        
        return $this->authorizationControllService->getSecurePostRequest($this->getPublicIdDomainHmac($corporateIdentification));
    }

    public function allowSetUserLoginProcess(RegistrationProcessDTO $authorizedUser): bool
    {
        $ok = $this->sslValidation($authorizedUser);

        if ($ok === 1) {
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

        return $this->logVerificationError($ok);
    }

    public function createUser(RegistrationProcessDTO $process)
    {
        $ok = $this->sslValidation($process);

        if ($ok === 1) {

            $user = new User();
            $user->setEmail($process->getEmail());
            $user->setProcess($process->getProcessId());
            $user->setPublicId($process->getPublicId());

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return true;
        }

       return  $this->logVerificationError($ok);        
    }    

    private function saveProcess($process, $authorizedData){
        $saveProcess = new Process();
        $processId = $process == 'user_registration'  ? 'registrationProcessId' : 'domainProcessId';
        $saveProcess->setProcessId($authorizedData[$processId]);
        $saveProcess->setAllowed(false);

        $this->entityManager->persist($saveProcess);
        $this->entityManager->flush();
    }

    private function getRequestPayload($publicId, $registrationIdentity, $domain, $userPublicId = null): array{
        return [
            'corporatePublicId' => $publicId,
            'corporateAuthentication' => $registrationIdentity,
            'domain' => $domain,
            'userPublicId' => $userPublicId
        ];
    }

    private function getPublicIdDomainHmac(array $corporateIdentification = []): array
    {
        if (empty($corporateIdentification)) {
            return [
                'publicId' => $this->instanceSettingsService->getInstancePublicIc(),
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

    private function sslValidation(RegistrationProcessDTO $process):int|false
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

    private function logVerificationError($ok): false
    {
        if ($ok === 0) {
            $this->logger->critical("The signature is invalid.");
        } else {
            $this->logger->critical("An error occurred during verification.");
        }
        return false;
    }    
}