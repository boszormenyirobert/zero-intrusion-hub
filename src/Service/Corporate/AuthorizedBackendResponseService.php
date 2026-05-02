<?php

namespace App\Service\Corporate;

use App\Helper\AuthorizationHelper;
use App\Logger\LogTrace;
use App\Service\Crypters\CrypterService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthorizedBackendResponseService
{
    private const HTTP_BAD_GATEWAY = 502;
    private const ERROR_INVALID_BACKEND_PAYLOAD = 'Invalid backend response payload.';
    private const ERROR_AUTHORIZATION_VALIDATION_FAILED = 'Authorization validation failed.';

    public function __construct(
        private LoggerInterface $logger,
        private AuthorizationHelper $authorizationHelper,
        private ContainerBagInterface $params
    ) {
    }

    public function decode(JsonResponse $response): array
    {
        $rawContent = $response->getContent();
        $responseSummary = LogTrace::summarizeStringContent($rawContent);

        $this->logIncomingResponse($response, $responseSummary);

        $data = $this->decodeResponseObject($rawContent, $responseSummary);
        $this->assertNoUpstreamErrorPayload($data, $responseSummary);
        $this->assertAuthorizedResponse($data, $response, $responseSummary);

        return $this->decryptCorporateIdentity($data->corporateIdentity);
    }

    private function logIncomingResponse(JsonResponse $response, array $responseSummary): void
    {
        $this->logger->info('Backend response received for authorization validation', [
            'status' => $response->getStatusCode(),
            'response_summary' => $responseSummary,
        ]);
    }

    private function decodeResponseObject(string $rawContent, array $responseSummary): object
    {
        $data = json_decode($rawContent);

        if (json_last_error() !== JSON_ERROR_NONE || !is_object($data)) {
            $this->logger->error('Backend response could not be decoded into an object', [
                'json_error' => json_last_error_msg(),
                'response_summary' => $responseSummary,
            ]);

            throw new HttpException(self::HTTP_BAD_GATEWAY, self::ERROR_INVALID_BACKEND_PAYLOAD);
        }

        return $data;
    }

    private function assertNoUpstreamErrorPayload(object $data, array $responseSummary): void
    {
        if (!isset($data->error) || (property_exists($data, 'corporateIdentity') && is_string($data->corporateIdentity))) {
            return;
        }

        $this->logger->error('Backend returned an upstream error payload instead of encrypted data', [
            'backend_error' => $data->error,
            'response_summary' => $responseSummary,
        ]);

        throw new HttpException(self::HTTP_BAD_GATEWAY, sprintf('Backend error: %s', $data->error));
    }

    private function assertAuthorizedResponse(object $data, JsonResponse $response, array $responseSummary): void
    {
        $authorized = $this->authorizationHelper->validateAuthorizationHeader($data, $response);

        if (($authorized['success'] ?? false) === true) {
            return;
        }

        $errorMessage = $authorized['error'] ?? self::ERROR_AUTHORIZATION_VALIDATION_FAILED;

        $this->logger->error('Encrypted backend response authorization failed', [
            'error' => $errorMessage,
            'response_summary' => $responseSummary,
        ]);

        throw new HttpException(self::HTTP_BAD_GATEWAY, $errorMessage);
    }

    private function decryptCorporateIdentity(string $corporateIdentity): array
    {
        $originalIdentity = new CrypterService($corporateIdentity, $this->params);

        return $originalIdentity->decryptData(true);
    }
}
