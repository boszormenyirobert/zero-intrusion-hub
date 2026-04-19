<?php

namespace App\Service\Corporate;

use App\Service\Corporate\AuthorizationControllService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Service\Corporate\DatabaseService;
use App\Service\User\UserRegistrationService;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionService
{
    public function __construct(
        private LoggerInterface $logger,
        private AuthorizationControllService $authorizationControllService,
        private RequestStack $requestStack,
        private DatabaseService $databaseService,
        private UserRegistrationService $userRegistrationService
    ) {}


    /**
     * Retrieves identifier data from API.
     *
     * Authorization headers: SERVICE_API_KEY, SERVICE_API_SECRET  
     * Encrypted using: DATA_HASH_SECRET
     */
    public function getSubscriptionData($process = "getIdentity", $businessModel="businessPro", $type, $publicId)
    {
         $contentJson = json_encode(
            [
                'businessModel' => $businessModel,
                'publicId' => $publicId,
                'scope' => $type
            ]);
        /** @var Response $response */
        $response = $this->userRegistrationService->forwardRegistration(
            [
                $process => $contentJson
            ]
        );

        $authorizedData = $this->authorizationControllService->controllAuthorization($response);
        if($type == 'internal'){
            $this->databaseService->createOwnClient($authorizedData);
        }
        
        return $authorizedData;
    }

    public function updateOwnClient($userInputs){
        $this->databaseService->updateOwnClient($userInputs);
    }

    public function getServiceAuthData(){
        $session = $this->requestStack->getSession();
        $authorizedData = $session->get('authorizedData');
        $session->remove('authorizedData');

        return $authorizedData;
    }

    public function finalizeSubscription(array $corporateData)
    {
        $this->logger->info('Starting HUB instance registration finalization', [
            'corporateDataKeys' => array_keys($corporateData),
        ]);
        $process = "updateIdentity";
        $response = $this->authorizationControllService->getSecurePostRequest(
            [$process => $corporateData]
        );

        return $response;
    }
}
