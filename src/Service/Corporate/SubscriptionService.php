<?php

namespace App\Service\Corporate;

use App\Service\Corporate\AuthorizationControllService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Service\Corporate\DatabaseService;
use App\Service\User\UserRegistrationService;
use App\Service\JWT\JwtService;

class SubscriptionService
{

    public function __construct(
        private LoggerInterface $logger,
        private AuthorizationControllService $authorizationControllService,
        private RequestStack $requestStack,
        private DatabaseService $databaseService,
        private UserRegistrationService $userRegistrationService,
        private JwtService $jwtService
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

        // $response = $this->authorizationControllService->getSecurePostRequest(
        //     [$process => ['initialization' => true]]
        // );

        $authorizedData = $this->authorizationControllService->controllAuthorization($response);
        $this->logger->critical('---------------'.json_encode($authorizedData));
        if($type == 'internal'){
            $this->databaseService->createOwnClient($authorizedData);
        }
        
       // $session = $this->requestStack->getSession();
       // $session->set('authorizedData', $authorizedData);
        
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
        $process = "updateIdentity";
        $response = $this->authorizationControllService->getSecurePostRequest(
            [$process => $corporateData]
        );

        return $response;
    }
}
