<?php

namespace App\Service\Instance;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Repository\OwnClientRepository;

class InstanceSettingsService
{
    private string $publicId;
    private string $domain;
    private string $secret;
    private string $corporateKey;
    private string $serviceApiKey;
    private string $serviceApiSecret;

    public function __construct(
        private ContainerBagInterface $params,
        private OwnClientRepository $repository
    ) {
        // Service reigstration
        $ownClient = $repository->findOneBy([], ['id' => 'ASC']); 
        if($ownClient){
            $this->publicId = $ownClient->getCorporateId();
            $this->secret = $ownClient->getCorporateIdSecret();
            $this->corporateKey = $ownClient->getCorporateIdKey();
        }

        // BASE SETTINGS TO SECURE COMMUNICATION BETWEEN THE SERVERS
        $this->domain = 'zeroproxyapi.local:8082';  
        $this->serviceApiKey = $params->get('SERVICE_API_KEY');
        $this->serviceApiSecret = $params->get('SERVICE_API_SECRET');

    }

    public function getInstancePublicIc(){
        return $this->publicId;
    }

    public function getInstanceDomain(){
        return $this->domain;
    }    

    public function getSecret()
    {
        return $this->secret;
    }

    public function getCorporateKey()
    {
        return $this->corporateKey;
    }

    public function getServiceApiKey()
    {
        return $this->serviceApiKey;
    }

    public function getServiceApiSecret()
    {
        return $this->serviceApiSecret;
    }
}