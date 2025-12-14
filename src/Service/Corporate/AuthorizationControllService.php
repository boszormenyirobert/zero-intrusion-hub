<?php

namespace App\Service\Corporate;

use App\Service\Crypters\CrypterService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Helper\AuthorizationHelper;
use Psr\Log\LoggerInterface;
use App\Service\Shared\RouteService;
use App\Service\Instance\InstanceSettingsService;

class AuthorizationControllService
{

    public function __construct(
        private HttpClientInterface $client,
        private ContainerBagInterface $params,
        private LoggerInterface $logger,
        private RouteService $routeService,
        private InstanceSettingsService $instanceSettingsService
    ) {}

    public function generateRequestIdentity(): string
    {
        $currentTimestamp = time();        
        
        $secret = $this->instanceSettingsService->getSecret();
        $message = $this->instanceSettingsService->getCorporateKey();

        return  hash_hmac('sha256', $message . '|' . $currentTimestamp, $secret);
    }

    public function controllAuthorization($response)
    {
        $data = json_decode($response->getContent());
        
        $authHelper = $this->getAuthorizationHelper();        
        $authorized = $authHelper->controllAuthorizationHeader($data, $response);

        if ($authorized['succes']) {
            // Content decryption
            $originalIdentity = new CrypterService($data->corporateIdentity, $this->params);
            $decodedJsonData = $originalIdentity->decryptData(true);           
            return $decodedJsonData;
        }

        return $authorized;
    }

    public function getSecurePostRequest(array $dataIntegrity)
    {
        $target = $this->routeService->mapRoute($dataIntegrity);
        $initEncryptedData = new CrypterService($dataIntegrity, $this->params);
        $authHelper = $this->getAuthorizationHelper();
        
        $response = $authHelper->buildRequest(
            $authHelper->getAuthHeader($initEncryptedData),
            $initEncryptedData->encryptData(),
            $target,            
            $dataIntegrity['X-Extension-Auth'] ?? null
        );

        return $response;
    }

    private function getAuthorizationHelper(): AuthorizationHelper
    {
        return new AuthorizationHelper(
            $this->client,
            $this->instanceSettingsService->getServiceApiSecret(),
            $this->instanceSettingsService->getServiceApiKey(),
            $this->logger
        );
    }    
}
