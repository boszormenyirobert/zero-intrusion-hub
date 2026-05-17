<?php

namespace App\Controller\CredentialHub;

use App\DTO\BackendPayloadDTO;
use App\Logger\LogTrace;
use App\Service\Security\ExtensionAuthGuard;
use App\Service\Shared\ProcessKey;
use App\Service\User\BackendForwardingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Service\CredentialHub\SharedSSE;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BackendForwarder
{
    private const ERROR_EMPTY_BODY = 'Empty request body';
    private const ERROR_MISSING_EXTENSION_AUTH = 'Missing X-Extension-Auth header!';
    private const ERROR_INVALID_JSON = 'Invalid JSON request body';
    private const ERROR_BACKEND_UNAVAILABLE = 'Backend unavailable';
    private const ERROR_INVALID_BACKEND_RESPONSE = 'Invalid backend response';
    private const ERROR_FORWARD_POLICY_MISSING = 'Forward policy missing';

    /**
     * Architectural note:
     * this forwarder is part of the HUB proxy/orchestration layer. For flows where
     * QR/HMAC material is issued by the upstream API and returned by frontend
     * clients, this component preserves routing and forwarding policy but does not
     * act as the final cryptographic validation authority for that client-facing
     * HMAC material. Final validation belongs to the upstream API unless a given
     * flow explicitly defines a different trust boundary.
     */

    /** @var array<string, array{require_extension_auth: bool, decode_body: bool}> */
    private const PROCESS_POLICIES = [
        ProcessKey::DOMAIN_READ_QR_IDENTITY => ['require_extension_auth' => false, 'decode_body' => true],
        ProcessKey::DOMAIN_READ_CREDENTIAL => ['require_extension_auth' => true, 'decode_body' => true],
        ProcessKey::DOMAIN_READ_CREDENTIAL_ENCRYPTED => ['require_extension_auth' => true, 'decode_body' => true],
        ProcessKey::DOMAIN_READ_STATE => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::DOMAIN_DELETE_QR_IDENTITY => ['require_extension_auth' => false, 'decode_body' => false],
        ProcessKey::DOMAIN_DELETE_CREDENTIAL => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::DOMAIN_DELETE_STATE => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::VAULT_READ_QR_IDENTITY => ['require_extension_auth' => false, 'decode_body' => true],
        ProcessKey::VAULT_READ_CREDENTIAL => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::VAULT_READ_CREDENTIAL_ENCRYPTED => ['require_extension_auth' => true, 'decode_body' => true],
        ProcessKey::VAULT_READ_STATE => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::VAULT_EDIT_QR_IDENTITY => ['require_extension_auth' => false, 'decode_body' => false],
        ProcessKey::VAULT_EDIT_CREDENTIAL => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::VAULT_EDIT_STATE => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::VAULT_DELETE_QR_IDENTITY => ['require_extension_auth' => false, 'decode_body' => false],
        ProcessKey::VAULT_DELETE_CREDENTIAL => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::VAULT_DELETE_STATE => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::SHARED_REGISTRATION_QR_IDENTITY => ['require_extension_auth' => false, 'decode_body' => false],
        ProcessKey::SHARED_REGISTRATION_NEW_TO_ENCRYPT => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::SHARED_REGISTRATION_NEW => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::SHARED_REGISTRATION_STATE => ['require_extension_auth' => true, 'decode_body' => false],
        ProcessKey::ONE_TOUCH_QR_IDENTITY => ['require_extension_auth' => false, 'decode_body' => true],
        ProcessKey::ONE_TOUCH_IDENTIFIER => ['require_extension_auth' => true, 'decode_body' => true],
        ProcessKey::ONE_TOUCH_STATE => ['require_extension_auth' => true, 'decode_body' => false],
    ];

    public static function forward(
        Request $request,
        BackendForwardingService $service,
        LoggerInterface $logger,
        string $process
    ): JsonResponse {
        $policy = self::resolvePolicy($process, $logger);

        if ($policy === null) {
            return self::jsonError(self::ERROR_FORWARD_POLICY_MISSING, 500);
        }

        return self::forwardWithPolicy(
            $request,
            $service,
            $logger,
            $process,
            $policy['require_extension_auth'],
            $policy['decode_body']
        );
    }

    public static function forwardWithPolicy(
        Request $request,
        BackendForwardingService $service,
        LoggerInterface $logger,
        string $process,
        bool $requireExtensionAuthHeader,
        bool $decodeBody
    ): JsonResponse {
        $extensionAuthHeader = $requireExtensionAuthHeader
            ? self::resolveExtensionAuthHeader($request)
            : null;

        return self::forwardPayload(
            $request,
            $service,
            $logger,
            $process,
            $decodeBody,
            $extensionAuthHeader,
            $requireExtensionAuthHeader
        );
    }

    public static function forwardWithHmac(
        Request $request,
        BackendForwardingService $service,
        LoggerInterface $logger,
        string $process,
        ?string $hmacHeader = null,
        bool $decodeBody = true
    ): JsonResponse {
        return self::forwardPayload(
            $request,
            $service,
            $logger,
            $process,
            $decodeBody,
            $hmacHeader,
            false
        );
    }

    public static function forwardSSE(
        string $key,
        SharedSSE $sharedSSE
    ): StreamedResponse {
        return $sharedSSE->handle($key);
    }

    private static function forwardPayload(
        Request $request,
        BackendForwardingService $service,
        LoggerInterface $logger,
        string $process,
        bool $decodeBody,
        ?string $extensionAuthHeader,
        bool $requireExtensionAuthHeader
    ): JsonResponse {
        $start = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        $body = $request->getContent();

        self::logIncomingRequest($request, $logger, $process, $requestId);

        if ($response = self::buildEarlyErrorResponse($body, $logger, $process, $requestId, $extensionAuthHeader, $requireExtensionAuthHeader)) {
            return $response;
        }

        try {
            $payload = self::buildPayload($process, $body, $decodeBody, $extensionAuthHeader);
        } catch (\JsonException $exception) {
            $logger->error('Invalid request JSON received for backend forwarding', [
                'process' => $process,
                'request_id' => $requestId,
                'error' => $exception->getMessage(),
            ]);

            return self::jsonError(self::ERROR_INVALID_JSON, 400);
        }

        $logger->debug('Payload built', [
            'process' => $process,
            'request_id' => $requestId,
        ]);

        try {
            $response = $service->forwardRegistration($payload->toArray());
            $logger->info('Forward registration response received', [
                'process' => $process,
                'status' => $response->getStatusCode(),
                'response_summary' => LogTrace::summarizeStringContent($response->getContent(false)),
            ]);
        } catch (\Throwable $exception) {
            $logger->error('Backend transport failure', [
                'process' => $process,
                'request_id' => $requestId,
                'error' => $exception->getMessage(),
            ]);

            return self::jsonError(self::ERROR_BACKEND_UNAVAILABLE, 503);
        }

        $content = $response->getContent(false);
        $status = $response->getStatusCode();
        $decoded = self::decodeBackendResponse($content);

        if ($decoded === null) {
            $logger->error('Invalid backend JSON response', [
                'process' => $process,
                'request_id' => $requestId,
                'status' => $status,
                'response_summary' => LogTrace::summarizeStringContent($content),
            ]);

            return self::jsonError(self::ERROR_INVALID_BACKEND_RESPONSE, 502);
        }

        $duration = round((microtime(true) - $start) * 1000);
        $logger->info('Forward success', [
            'process' => $process,
            'request_id' => $requestId,
            'status' => $status,
            'duration_ms' => $duration,
        ]);

        return new JsonResponse($decoded, $status);
    }

    private static function decodeBackendResponse(string $content): ?array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private static function logIncomingRequest(
        Request $request,
        LoggerInterface $logger,
        string $process,
        string $requestId
    ): void {
        $route = $request->attributes->get('_route');

        $logger->info('Forward request received', [
            'process' => $process,
            'request_id' => $requestId,
            'route' => $route,
            'method' => $request->getMethod(),
            'content_length' => strlen($request->getContent()),
            'remote_addr_hash' => LogTrace::fingerprint($request->getClientIp()),
            'user_agent_hash' => LogTrace::fingerprint($request->headers->get('User-Agent')),
            'has_extension_auth' => $request->headers->has('X-Extension-Auth'),
            'origin' => $request->headers->get('Origin'),
            'referer' => $request->headers->get('Referer'),
        ]);

        if (is_string($route) && $route !== $process) {
            $logger->warning('Route name and forwarded process differ', [
                'process' => $process,
                'route' => $route,
                'request_id' => $requestId,
            ]);
        }
    }

    private static function buildPayload(
        string $process,
        string $body,
        bool $decodeBody,
        ?string $extensionAuthHeader
    ): BackendPayloadDTO {
        $payload = [
            $process => $decodeBody ? json_decode($body, true, 512, JSON_THROW_ON_ERROR) : $body,
        ];

        if ($extensionAuthHeader) {
            $payload['X-Extension-Auth'] = $extensionAuthHeader;
        }

        return BackendPayloadDTO::fromArray($payload);
    }

    private static function buildEarlyErrorResponse(
        string $body,
        LoggerInterface $logger,
        string $process,
        string $requestId,
        ?string $extensionAuthHeader,
        bool $requireExtensionAuthHeader
    ): ?JsonResponse {
        if ($body === '') {
            $logger->info('Empty request body', [
                'process' => $process,
                'request_id' => $requestId,
            ]);

            return self::jsonError(self::ERROR_EMPTY_BODY, 400);
        }

        if ($requireExtensionAuthHeader && !$extensionAuthHeader) {
            $logger->info('Missing extension auth header', [
                'process' => $process,
                'request_id' => $requestId,
            ]);

            //    return self::jsonError(self::ERROR_MISSING_EXTENSION_AUTH, 401);
            return null;
        }

        return null;
    }

    private static function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    private static function resolveExtensionAuthHeader(Request $request): ?string
    {
        $validatedHeader = $request->attributes->get(ExtensionAuthGuard::REQUEST_ATTRIBUTE);

        if (is_string($validatedHeader) && trim($validatedHeader) !== '') {
            return trim($validatedHeader);
        }

        $header = $request->headers->get('X-Extension-Auth');

        return is_string($header) && trim($header) !== '' ? trim($header) : null;
    }

    /** @return array{require_extension_auth: bool, decode_body: bool}|null */
    private static function resolvePolicy(string $process, LoggerInterface $logger): ?array
    {
        if (isset(self::PROCESS_POLICIES[$process])) {
            return self::PROCESS_POLICIES[$process];
        }

        $logger->error('Forward policy is missing for credential hub process', [
            'process' => $process,
        ]);

        return null;
    }
}
