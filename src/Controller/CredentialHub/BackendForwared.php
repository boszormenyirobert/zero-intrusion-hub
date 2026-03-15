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
            $body = $request->getContent();

            if (empty($body)) {
                return new JsonResponse(['error' => 'Empty request body'], 400);
            }

            // Decide if we keep the raw string or decode JSON
            $payload = [$process => $decodeBody ? json_decode($body, true) : $body];

            if ($withHeader) {
                $header = $request->headers->get('X-Extension-Auth');
                if (!$header) {
                    return new JsonResponse(['error' => 'Missing X-Extension-Auth header!'], 401);
                }
                $payload['X-Extension-Auth'] = $header;
            }

            try {
                $response = $service->forwardRegistration($payload);
            } catch (\Throwable $e) {
                $logger->error('Backend transport failure', [
                    'process' => $process,
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
        $body = $request->getContent();

        if (empty($body)) {
            return new JsonResponse(['error' => 'Empty request body'], 400);
        }

        $payload = [$process => $decodeBody ? json_decode($body, true) : $body];

        if ($hmacHeader) {
            $payload['X-Extension-Auth'] = $hmacHeader;
        }

        try {
            $response = $service->forwardRegistration($payload);
        } catch (\Throwable $e) {
            $logger->error('Backend transport failure', [
                'process' => $process,
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

        return new JsonResponse($decoded, $status);
    }         
}