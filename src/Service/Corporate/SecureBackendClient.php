<?php

namespace App\Service\Corporate;

use App\Helper\AuthorizationHelper;
use App\Service\Crypters\CrypterService;
use App\Service\Shared\RouteService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class SecureBackendClient
{
    private const ERROR_ROUTE_MAPPING_MISSING = 'Backend route mapping missing.';
    private const RESPONSE_KEY_ERROR = 'error';
    private const RESPONSE_KEY_SUCCESS = 'success';
    private const RESPONSE_KEY_VALIDATION = 'validation';
    private const EXTENSION_AUTH_HEADER = 'X-Extension-Auth';

    public function __construct(
        private RouteService $routeService,
        private AuthorizationHelper $authorizationHelper,
        private ContainerBagInterface $params,
        private LoggerInterface $logger
    ) {
    }

    public function post(array $dataIntegrity): JsonResponse
    {
        $target = $this->routeService->mapRoute($dataIntegrity);

        if ($target === '') {
            return $this->createMissingRouteResponse($dataIntegrity);
        }

        $this->logOutgoingRequest($target);

        $encryptedPayload = $this->encryptPayload($dataIntegrity);
        $ivBase64 = $this->authorizationHelper->generateIvBase64();
        $authorization = $this->authorizationHelper->getAuthHeader($encryptedPayload, $ivBase64);

        $response = $this->authorizationHelper->buildRequest(
            $authorization,
            $encryptedPayload,
            $target,
            $ivBase64,
            $this->extractForwardedExtensionAuthHeader($dataIntegrity)
        );

        $this->logResponse($response);

        return $response;
    }

    private function createMissingRouteResponse(array $dataIntegrity): JsonResponse
    {
        $this->logger->error('Unable to resolve backend route for secure POST request.', [
            'payload_keys' => array_keys($dataIntegrity),
        ]);

        return new JsonResponse([
            self::RESPONSE_KEY_ERROR => self::ERROR_ROUTE_MAPPING_MISSING,
        ], 500);
    }

    private function logOutgoingRequest(string $target): void
    {
        $this->logger->info('Forwarding secure POST request', [
            'target' => $target,
        ]);
    }

    private function encryptPayload(array $dataIntegrity): string
    {
        return (new CrypterService($dataIntegrity, $this->params))->encryptData();
    }

    private function extractForwardedExtensionAuthHeader(array $dataIntegrity): ?string
    {
        $header = $dataIntegrity[self::EXTENSION_AUTH_HEADER] ?? null;

        return is_string($header) && $header !== '' ? $header : null;
    }

    private function logResponse(JsonResponse $response): void
    {
        try {
            $decoded = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->logger->info('Secure POST request response received', [
                'status' => $response->getStatusCode(),
                'success' => $decoded[self::RESPONSE_KEY_SUCCESS] ?? 'No success field in response',
                'userValidation' => $decoded[self::RESPONSE_KEY_VALIDATION] ?? 'No userValidation field in response',
            ]);
        } catch (\JsonException $exception) {
            $this->logger->error('Error processing backend response', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
