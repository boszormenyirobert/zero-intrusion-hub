<?php

namespace App\Controller\CredentialHub;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;
use App\Service\User\UserRegistrationService;

class BackendForwared
{
    /**
     * Forward request to backend with optional JSON decode and header
     *
     * @param Request $request
     * @param UserRegistrationService $service
     * @param LoggerInterface $logger
     * @param string $process
     * @param bool $withHeader Add X-Extension-Auth header
     * @param bool $decodeBody Decode request body JSON
     * @return JsonResponse
     */    
    public static function forward(
            Request $request,
            UserRegistrationService $service,
            LoggerInterface $logger,
            string $process,
            bool $withHeader = false,
            bool $decodeBody = false
        ): JsonResponse {
            $start = microtime(true);
            // Generate a unique request ID for logging
            $requestId = bin2hex(random_bytes(8));

            $body = $request->getContent();

            if (empty($body)) {
                $logger->info('Empty request body', [
                    'process' => $process,
                    'request_id' => $requestId
                ]);
                return new JsonResponse(['error' => 'Empty request body'], 400);
            }

            // Decide if we keep the raw string or decode JSON
            $payload = [$process => $decodeBody ? json_decode($body, true) : $body];

            $logger->debug('Payload built', [
                'process' => $process,
                'request_id' => $requestId
            ]);

            if ($withHeader) {
                $header = $request->headers->get('X-Extension-Auth');
                if (!$header) {
                    $logger->info('Missing extension auth header', [
                        'process' => $process,
                        'request_id' => $requestId
                    ]);
                    return new JsonResponse(['error' => 'Missing X-Extension-Auth header!'], 401);
                }
                $payload['X-Extension-Auth'] = $header;
            }

            try {
                $response = $service->forwardRegistration($payload);
            } catch (\Throwable $e) {
                $logger->error('Backend transport failure', [
                    'process' => $process,
                    'request_id' => $requestId,
                    'error' => $e->getMessage()
                ]);                

                return new JsonResponse(['error' => 'Backend unavailable'], 503);
            }

            $content = $response->getContent(false);
            $status = $response->getStatusCode();

            $decoded = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $logger->error('Invalid backend JSON response', [
                    'process' => $process,
                    'status' => $status,
                    'content' => $content
                ]);
                return new JsonResponse(['error' => 'Invalid backend response'], 502);
            }
            $duration = round((microtime(true) - $start) * 1000);
            $logger->info('Forward success', [
                'process' => $process,
                'request_id' => $requestId,
                'status' => $status,
                'duration_ms' => $duration
            ]);

            return new JsonResponse($decoded, $status);
        }
/**
     * Forward request with explicit HMAC header (for NFC / X-Client-Auth)
     *
     * @param Request $request
     * @param UserRegistrationService $service
     * @param LoggerInterface $logger
     * @param string $process
     * @param string|null $hmacHeader
     * @param bool $decodeBody
     * @return JsonResponse
     */
    public static function forwardWithHmac(
        Request $request,
        UserRegistrationService $service,
        LoggerInterface $logger,
        string $process,
        ?string $hmacHeader = null,
        bool $decodeBody = true
    ): JsonResponse {
        $start = microtime(true);
        $requestId = bin2hex(random_bytes(8));

        $body = $request->getContent();

        if (empty($body)) {
            $logger->info('Empty request body', [
                'process' => $process,
                'request_id' => $requestId
            ]);
            return new JsonResponse(['error' => 'Empty request body'], 400);
        }

        $payload = [$process => $decodeBody ? json_decode($body, true) : $body];

        $logger->debug('Payload built', [
            'process' => $process,
            'request_id' => $requestId
        ]);

        if ($hmacHeader) {
            $payload['X-Extension-Auth'] = $hmacHeader;
        }

        try {
            $response = $service->forwardRegistration($payload);
        } catch (\Throwable $e) {
            $logger->error('Backend transport failure', [
                'process' => $process,
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);
            return new JsonResponse(['error' => 'Backend unavailable'], 503);
        }

        $content = $response->getContent(false);
        $status = $response->getStatusCode();

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $logger->error('Invalid backend JSON response', [
                'process' => $process,
                'request_id' => $requestId,
                'status' => $status,
                'content' => $content
            ]);
            return new JsonResponse(['error' => 'Invalid backend response'], 502);
        }

        $duration = round((microtime(true) - $start) * 1000);
        $logger->info('Forward success', [
            'process' => $process,
            'request_id' => $requestId,
            'status' => $status,
            'duration_ms' => $duration
        ]);

        return new JsonResponse($decoded, $status);
    }         
}