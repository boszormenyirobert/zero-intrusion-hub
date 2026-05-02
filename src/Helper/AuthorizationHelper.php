<?php

namespace App\Helper;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AuthorizationHelper
{
    private const REQUEST_TIMEOUT_SECONDS = 10.0;
    private const DEFAULT_AUTH_TIMESTAMP_TTL_SECONDS = 300;
    private const HEADER_CONTENT_TYPE = 'Content-Type';
    private const HEADER_X_AUTH = 'X-Auth';
    private const HEADER_X_EXTENSION_AUTH = 'X-Extension-Auth';
    private const AUTH_SCHEME = 'HMAC';
    private const ERROR_INVALID_RESPONSE_PAYLOAD = 'Invalid response payload';
    private const ERROR_MISSING_CORPORATE_IDENTITY = 'Missing corporateIdentity in response payload';
    private const ERROR_MISSING_IV = 'Missing IV in response';
    private const ERROR_MISSING_X_AUTH = 'Missing X-Auth header';
    private const ERROR_INVALID_AUTH_HEADER = 'Invalid Authorization header';
    private const ERROR_INVALID_AUTH_TIMESTAMP = 'Invalid X-Auth timestamp';
    private const ERROR_EXPIRED_AUTH_TIMESTAMP = 'Expired X-Auth timestamp';
    private const ERROR_UNKNOWN_API_KEY = 'Unknown API key';
    private const ERROR_INVALID_HMAC_SIGNATURE = 'Invalid HMAC signature';
    private const ERROR_REQUEST_FAILED = 'Request failed';
    private const ERROR_UPSTREAM_REQUEST_FAILED = 'Upstream request failed';
    private const ERROR_UPSTREAM_TRANSPORT_FAILED = 'Upstream transport failed';
    private const ERROR_UPSTREAM_RESPONSE_HANDLING_FAILED = 'Upstream response handling failed';

    /**
     * Sets up the helper with API keys, secret, and logger for HMAC and encryption operations.
    * Called from: SecureRequestService and related backend gateway services.
     */
    public function __construct(
        private HttpClientInterface $client,
        private string $service_api_secret,
        private string $service_api_key,
        private LoggerInterface $logger,
        private int $xAuthTimestampTtl = self::DEFAULT_AUTH_TIMESTAMP_TTL_SECONDS,
    ) {
    }

    /**
     * Generates an HMAC authorization header for a given encrypted data payload.
     * Used to authenticate requests to the backend API.
        * Called from: `SecureBackendClient` when building outbound backend requests.
     *
     * @return string HMAC authorization header
     */
    public function getAuthHeader(string $encryptedDataValue, string $ivBase64): string
    {
        $message = $this->formatMessage($encryptedDataValue, $ivBase64);
        $signature = $this->buildSignature($message);

        return $this->createHmac($signature);
    }

    private function formatMessage(string $encryptedDataValue, string $ivBase64): string
    {
        return "$encryptedDataValue|$ivBase64";
    }

    private function buildSignature(string $message): string
    {
        return hash_hmac('sha256', $message, $this->service_api_secret);
    }

    private function createHmac(string $signature): string
    {
        return self::AUTH_SCHEME . ' ' . $this->service_api_key . ':' . $signature . ':' . time();
    }

    /**
     * Generates and returns a new random IV (base64 encoded) for encryption.
     * Used internally during construction and for each request.
     *
     * @return string Base64-encoded IV
     */
    public function generateIvBase64(): string
    {
        $iv = openssl_random_pseudo_bytes(16);
        return base64_encode($iv);
    }
    /**
     * Validates the HMAC authorization header in a backend API response.
     * Checks the HMAC signature and API key, returns success or error details.
    * Called from: `AuthorizedBackendResponseService`.
     *
     * @param Response $response The HTTP response from the backend API
     * @return array Success or error details
     */
    public function validateAuthorizationHeader(object $data, Response $response): array
    {
        if (!property_exists($data, 'corporateIdentity') || !is_string($data->corporateIdentity) || $data->corporateIdentity === '') {
            return $this->failAuthorizationValidation(self::ERROR_MISSING_CORPORATE_IDENTITY, [
                'payload_keys' => array_keys(get_object_vars($data)),
            ]);
        }

        $encryptedData = $data->corporateIdentity;
        $decodedJsonData = $this->decodeResponsePayload($response);

        if ($decodedJsonData === null) {
            return $this->failAuthorizationValidation(self::ERROR_INVALID_RESPONSE_PAYLOAD);
        }

        if (!isset($decodedJsonData['iv'])) {
            return $this->failAuthorizationValidation(self::ERROR_MISSING_IV);
        }

        $ivBase64 = $decodedJsonData['iv'];

        $parsedAuthHeader = $this->parseAuthorizationHeader($response->headers->all());

        if (($parsedAuthHeader['success'] ?? false) !== true) {
            return $parsedAuthHeader;
        }

        $apiKey = $parsedAuthHeader['apiKey'];
        $receivedSignature = $parsedAuthHeader['signature'];

        $expectedSignature = hash_hmac('sha256', $this->formatMessage($encryptedData, $ivBase64), $this->resolveSecretKeyStore()[$apiKey]);

        if (!hash_equals($expectedSignature, $receivedSignature)) {
            return $this->failAuthorizationValidation(self::ERROR_INVALID_HMAC_SIGNATURE);
        }

        return ['success' => true];
    }

    /**
     * Builds and sends a POST request to the backend API with encrypted data and HMAC authorization.
     * Handles response parsing and error handling, returns a JsonResponse.
    * Called from: `SecureBackendClient`.
     *
     * @param string $authorization HMAC authorization header
     * @param string $target The backend API endpoint
     * @param string|null $forwardedAuthHeader Optional forwarded extension auth header
     * @return JsonResponse The backend API response as JSON
     */
    public function buildRequest(
        string $authorization,
        string $encryptedData,
        string $target,
        string $ivBase64,
        ?string $forwardedAuthHeader = null
    ): JsonResponse {
        $header = $this->buildRequestHeaders($authorization, $forwardedAuthHeader);
        $payload = $this->buildRequestPayload($encryptedData, $ivBase64);
        $requestStartedAt = microtime(true);

        $this->logger->info('Outbound HTTP request started', [
            'target' => $target,
            'has_forwarded_extension_auth' => $forwardedAuthHeader !== null,
        ]);

        try {
            $response = $this->client->request('POST', $target, [
                'headers' => $header,
                'body' => json_encode($payload, \JSON_THROW_ON_ERROR),
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
            ]);

            $handledResponse = $this->handleResponse($response);

            $this->logger->info('Outbound HTTP request finished', [
                'target' => $target,
                'status' => $handledResponse->getStatusCode(),
                'duration_ms' => round((microtime(true) - $requestStartedAt) * 1000, 2),
            ]);

            return $handledResponse;

        } catch (ClientExceptionInterface $e) {
            return $this->createHttpClientExceptionResponse(
                'Outbound HTTP request failed with client exception',
                $target,
                $requestStartedAt,
                $e,
                self::ERROR_REQUEST_FAILED,
                403
            );
        } catch (ServerExceptionInterface|RedirectionExceptionInterface $e) {
            return $this->createHttpClientExceptionResponse(
                'Outbound HTTP request failed with upstream HTTP exception',
                $target,
                $requestStartedAt,
                $e,
                self::ERROR_UPSTREAM_REQUEST_FAILED,
                502
            );
        } catch (TransportExceptionInterface $e) {
            return $this->createHttpClientExceptionResponse(
                'Outbound HTTP request failed with transport exception',
                $target,
                $requestStartedAt,
                $e,
                self::ERROR_UPSTREAM_TRANSPORT_FAILED,
                503
            );
        } catch (DecodingExceptionInterface $e) {
            return $this->createHttpClientExceptionResponse(
                'Outbound HTTP request failed while decoding upstream response',
                $target,
                $requestStartedAt,
                $e,
                self::ERROR_UPSTREAM_RESPONSE_HANDLING_FAILED,
                502
            );
        }
    }

    private function createHttpClientExceptionResponse(
        string $logMessage,
        string $target,
        float $requestStartedAt,
        \Throwable $exception,
        string $publicError,
        int $statusCode
    ): JsonResponse {
        $this->logger->error($logMessage, [
            'target' => $target,
            'duration_ms' => round((microtime(true) - $requestStartedAt) * 1000, 2),
            'error' => $exception->getMessage(),
            'exception_class' => $exception::class,
        ]);

        return new JsonResponse([
            'error' => $publicError,
            'status' => $statusCode,
        ], $statusCode);
    }

    private function buildRequestHeaders(string $authorization, ?string $forwardedAuthHeader = null): array
    {
        $header = [
            self::HEADER_CONTENT_TYPE => 'application/json',
            self::HEADER_X_AUTH => $authorization
        ];

        if ($forwardedAuthHeader) {
            $header[self::HEADER_X_EXTENSION_AUTH] = $forwardedAuthHeader;
        }

        return $header;
    }

    private function buildRequestPayload(string $encryptedData, string $ivBase64): array
    {
        return [
            'zeroIntrusionProyApi' => $encryptedData,
            'iv' => $ivBase64
        ];
    }

    /**
     * Handles the HTTP response, decodes JSON, and passes along X-Auth if present.
     */
    private function handleResponse(ResponseInterface $response): JsonResponse
    {
        $statusCode = $response->getStatusCode();
        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
        $content = $response->getContent(false);

        // If JSON response
        if (str_contains($contentType, 'application/json')) {
            $decoded = $this->decodeJsonContent($content);

            if ($decoded !== null) {
                $responseToReturn = new JsonResponse($decoded, $statusCode);

                if ($response->getHeaders(false)['x-auth'][0] ?? false) {
                    $responseToReturn->headers->set(self::HEADER_X_AUTH, $response->getHeaders(false)['x-auth'][0]);
                }

                return $responseToReturn;
            }
        }

        return new JsonResponse([
            'error' => 'Non-JSON or invalid response',
            'status' => 403,
        ], 403);
    }

    private function decodeResponsePayload(Response $response): ?array
    {
        return $this->decodeJsonContent($response->getContent());
    }

    private function parseAuthorizationHeader(array $headers): array
    {
        $authHeader = $headers['x-auth'][0] ?? null;

        if (!$authHeader) {
            return $this->failAuthorizationValidation(self::ERROR_MISSING_X_AUTH);
        }

        $matches = preg_split("/[ :]+/", $authHeader);

        if (!is_array($matches) || count($matches) < 4 || $matches[0] !== self::AUTH_SCHEME) {
            return $this->failAuthorizationValidation(self::ERROR_INVALID_AUTH_HEADER);
        }

        [$apiKey, $receivedSignature, $timestamp] = [$matches[1], $matches[2], $matches[3]];

        $isFreshTimestamp = is_string($timestamp) && ctype_digit($timestamp)
            ? $this->isTimestampFresh((int) $timestamp)
            : false;

        $this->logger->info('Received X-Auth header for validation', [
            'timestamp' => $timestamp,
            'is_timestamp_fresh' => $isFreshTimestamp,
        ]);

        if (!is_string($timestamp) || !ctype_digit($timestamp)) {
            return $this->failAuthorizationValidation(self::ERROR_INVALID_AUTH_TIMESTAMP);
        }

        if (!$isFreshTimestamp) {
            return $this->failAuthorizationValidation(self::ERROR_EXPIRED_AUTH_TIMESTAMP);
        }

        if (!isset($this->resolveSecretKeyStore()[$apiKey])) {
            return $this->failAuthorizationValidation(self::ERROR_UNKNOWN_API_KEY);
        }

        return [
            'success' => true,
            'apiKey' => $apiKey,
            'signature' => $receivedSignature,
        ];
    }

    private function isTimestampFresh(int $timestamp): bool
    {
        return $timestamp >= (time() - $this->xAuthTimestampTtl);
    }

    private function resolveSecretKeyStore(): array
    {
        return [
            $this->service_api_key => $this->service_api_secret,
        ];
    }

    private function failAuthorizationValidation(string $error, array $context = []): array
    {
        $this->logger->error('Control authorization header failed', [
            'error' => $error,
            ...$context,
        ]);

        return [
            'success' => false,
            'error' => $error,
        ];
    }

    private function decodeJsonContent(string $content): ?array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('JSON decoding failed while processing HTTP payload', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
