<?php

namespace App\Helper;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

final class AuthorizationHelper
{
    private $service_api_key = null;
    private $service_api_secret = null;
    private $ivBase64 = null;
    private HttpClientInterface $client;
    private $logger;

    /**
     * Constructor for AuthorizationHelper.
     *
     * Sets up the helper with API keys, secret, and logger for HMAC and encryption operations.
     *
     * Called from: AuthorizationControllService (getAuthorizationHelper)
     */
    public function __construct(
        HttpClientInterface $client,
        $service_api_secret,
        $service_api_key,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->service_api_secret = $service_api_secret;
        $this->service_api_key = $service_api_key;
        $this->ivBase64 = $this->setIvBase64();
        $this->logger = $logger;
    }

    // TODO extend to be able to use by the QR-API generation => All registrated corporate should use HMAC by QR-Code request
    /**
     * Generates an HMAC authorization header for a given encrypted data payload.
     *
     * Used to authenticate requests to the backend API.
     *
     * Called from: AuthorizationControllService (getSecurePostRequest)
     *
     * @param mixed $encryptedData The encrypted data object
     * @return string HMAC authorization header
     */
    public function getAuthHeader($encryptedData): string
    {
        $encryptedDataValue = $encryptedData->encryptData();

        $ivBase64 = $this->getIvBase64();

        $message = "$encryptedDataValue|$ivBase64";
        $signature = hash_hmac('sha256', $message, $this->service_api_secret);
        $authorization = 'HMAC ' . $this->service_api_key . ':' . $signature  . ':' . time();;

        return $authorization;
    }

    /**
     * Generates and returns a new random IV (base64 encoded) for encryption.
     *
     * Used internally during construction and for each request.
     *
     * @return string Base64-encoded IV
     */
    private function setIvBase64()
    {
        $iv = openssl_random_pseudo_bytes(16);
        return base64_encode($iv);
    }

    /**
     * Returns the current IV (base64 encoded) used for encryption.
     *
     * Called from: getAuthHeader, buildRequest
     *
     * @return string Base64-encoded IV
     */
    public function getIvBase64()
    {
        return $this->ivBase64;
    }
    /**
     * Validates the HMAC authorization header in a backend API response.
     *
     * Checks the HMAC signature and API key, returns success or error details.
     *
     * Called from: AuthorizationControllService (controllAuthorization)
     *
     * @param mixed $data The data object containing the encrypted identity
     * @param Response $response The HTTP response from the backend API
     * @return array Success or error details
     */
    public function controllAuthorizationHeader($data, $response)
    {
        $encryptedData = $data->corporateIdentity;
        $decodedJsonData = json_decode($response->getContent(), true);
        $ivBase64 = $decodedJsonData['iv'];

        // Header Authorization
        $service_api_secret = $this->service_api_secret;
        $service_api_key = $this->service_api_key;

        $secretKeyStore = [
            $service_api_key => $service_api_secret
        ];


        $data = json_decode($response->getContent(), true);
        $headers = $response->headers->all();
        $authHeader = $headers['x-auth'][0] ?? null;


        $matches = preg_split("/[ :]+/", $authHeader);

        if ($matches[0] !== "HMAC") {
            return ([
                'succes' => false,
                'error' => "Invalid Authorization header"
            ]);
        }

        [$apiKey, $recvSignature] = [$matches[1], $matches[2]];

        if (!isset($secretKeyStore[$apiKey])) {
            return ([
                'succes' => false,
                'error' => 'Unknown API key'
            ]);
        }


        $secretKey = $secretKeyStore[$apiKey];
        $message = "$encryptedData|$ivBase64";
        $expectedSignature = hash_hmac('sha256', $message, $secretKey);


        if (!hash_equals($expectedSignature, $recvSignature)) {
            return ([
                'succes' => false,
                'error' => 'Invalid HMAC signature'
            ]);
        }

        return ['succes' => true];
    }

    /**
     * Builds and sends a POST request to the backend API with encrypted data and HMAC authorization.
     *
     * Handles response parsing and error handling, returns a JsonResponse.
     *
     * Called from: AuthorizationControllService (getSecurePostRequest)
     *
     * @param string $authorization HMAC authorization header
     * @param mixed $encryptedData The encrypted data payload
     * @param string $target The backend API endpoint
     * @param string|null $forwardedAuthHeader Optional forwarded extension auth header
     * @return JsonResponse The backend API response as JSON
     */
    public function buildRequest(string $authorization, $encryptedData, $target, $forwardedAuthHeader = null)
    {
        $header = [
            'Content-Type' => 'application/json',
            'X-Auth' => $authorization
        ];

        if ($forwardedAuthHeader) {
            $header['X-Extension-Auth'] = $forwardedAuthHeader;
        }

        $payload = [
            'zeroIntrusionProyApi' => $encryptedData,
            'iv' => $this->getIvBase64()
        ];

        try {
            $response = $this->client->request('POST', $target, [
                'headers' => $header,
                'body' => json_encode($payload, \JSON_THROW_ON_ERROR)
            ]);

            $statusCode = $response->getStatusCode();
            $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
            $content = $response->getContent(false);

            // If JSON response
            if (str_contains($contentType, 'application/json')) {
                $decoded = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $responseToReturn = new JsonResponse($decoded, $statusCode);

                    if ($response->getHeaders(false)['x-auth'][0] ?? false) {
                        $responseToReturn->headers->set('X-Auth', $response->getHeaders(false)['x-auth'][0]);
                    }

                    return $responseToReturn;
                }
            }

            // If not JSON or invalid
            return new JsonResponse([
                'error' => 'Non-JSON or invalid response',
                'status' => 403,
                'raw' => 'content'
            ], 403);

        } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
            // $response = $e->getResponse();
            // $content = $response ? $response->getContent(false) : 'Unknown error';
            // $statusCode = $response ? $response->getStatusCode() : 500;

            return new JsonResponse([
                'error' => 'Request failed',
                'status' => 403,
                'responseBody' => 'content'
            ], 403);
        }
    }        
}
