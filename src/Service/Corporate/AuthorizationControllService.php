<?php

namespace App\Service\Corporate;

use App\Service\Crypters\CrypterService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Helper\AuthorizationHelper;
use Psr\Log\LoggerInterface;
use App\Service\Shared\RouteService;
use App\Service\Instance\InstanceSettingsService;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthorizationControllService
{

    /**
     * @param HttpClientInterface $client HTTP client for backend API calls
     * @param ContainerBagInterface $params Parameter bag for configuration
     * @param LoggerInterface $logger Logger for debug and error logging
     * @param RouteService $routeService Service for mapping process keys to backend routes
     * @param InstanceSettingsService $instanceSettingsService Service for retrieving instance/corporate secrets and keys
     */
    public function __construct(
        private HttpClientInterface $client,
        private ContainerBagInterface $params,
        private LoggerInterface $logger,
        private RouteService $routeService,
        private InstanceSettingsService $instanceSettingsService
    ) {}

    /**
     * Generates a unique HMAC-based request identity for the current timestamp and corporate key.
     * Used for HMAC authentication in API requests.
     *
     * Called from: UserService (getPublicIdDomainHmac), and anywhere a new request identity is needed for secure API calls.
     *
     * @return string HMAC signature for the request
     */
    public function generateRequestIdentity(): string
    {
        $currentTimestamp = time();        
        
        $secret = $this->instanceSettingsService->getSecret();
        $message = $this->instanceSettingsService->getCorporateKey();

        return  hash_hmac('sha256', $message . '|' . $currentTimestamp, $secret);
    }

    /**
     * Validates the authorization of a backend API response and decrypts the content if authorized.
     * Uses AuthorizationHelper to check HMAC headers and decrypts the corporate identity if successful.
     *
     * Called from: SubscriptionService (getSubscriptionData), UserService (getQrCode, getNfcUsers), 
     * and other services after receiving a backend response.
     *
     * @return mixed Decrypted data if authorized, or an array with error details
     */
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

    
    /**
     * Builds and sends a secure POST request to the backend API with encrypted data and HMAC authorization.
     * Uses AuthorizationHelper to build the request, encrypt data, and set headers. Returns the backend response.
     * Called from: UserRegistrationService (forwardRegistration), Device\ReplaceDeviceService (forwardRegistration), 
     * UserService (getQrCode, getNfcUsers), and other services needing secure backend communication.
     *
     * @param array $dataIntegrity The data to be encrypted and sent
     * @return JsonResponse The HTTP response from the backend API
     */
    public function getSecurePostRequest(array $dataIntegrity): JsonResponse
    {
        $target = $this->routeService->mapRoute($dataIntegrity);
        $this->logger->info('Forwarding secure POST request', [
            'target' => $target
        ]);
        $initEncryptedData = new CrypterService($dataIntegrity, $this->params);
        $authHelper = $this->getAuthorizationHelper();
        
        $response = $authHelper->buildRequest(
            $authHelper->getAuthHeader($initEncryptedData),
            $initEncryptedData->encryptData(),
            $target,            
            $dataIntegrity['X-Extension-Auth'] ?? null
        );
        
        try{
            $decoded = json_decode($response->getContent(), true);
            $this->logger->info('Secure POST request response received', [
                'status' => $response->getStatusCode(),
                'success' => $decoded['success'] ?? 'No success field in response',
                'userValidation' => $decoded['validation'] ?? 'No userValidation field in response'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error processing backend response', [
                'error' => $e->getMessage()
            ]);  
        }      

        return $response;
    }

    /**
     * Instantiates and returns an AuthorizationHelper for HMAC and encryption operations.
     * Used internally by this service for building requests and validating responses.
     *
     * @return AuthorizationHelper
     */
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
