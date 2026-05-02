<?php

/**
 * Handles NFC-related API endpoints for device management.
 * - Provides Desktop Application endpoints to fetch all NFC users.
 * - Handles decryption of NFC card data and prepares it for QR code generation.
 * - Forwards requests to the backend via the dedicated backend forwarding service.
 * - Ensures request data integrity using client authentication headers (HMAC).
 *
 * Trust boundary note:
 * - The HUB enforces route policy and header presence for `X-Client-Auth`.
 * - Final cryptographic validation of client-auth HMAC material is performed exclusively by the upstream API.
 */

namespace App\Controller\DeviceManagement\Nfc\Api;

use App\Attribute\ClientAuthRequired;
use App\Controller\Shared\AbstractBackendForwardingController;
use App\Service\Security\ClientAuthRequestResolver;
use App\Service\Shared\ProcessKey;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\User\BackendForwardingService;

class NfcController extends AbstractBackendForwardingController
{
    public function __construct(
        private LoggerInterface $logger,
        private ?ClientAuthRequestResolver $clientAuthRequestResolver = null,
    ) {
    }

    /*
    * API endpoint used by the Desktop Application to fetch all NFC users.
    * The encrypted NFC data from the selected user will be written onto the NFC card by the Desktop Application.
    */
    #[ClientAuthRequired]
    #[Route('/api/nfc/users', name: ProcessKey::API_NFC_USERS, methods: "POST")]
    public function getNfcUsers(
        Request $request,
        BackendForwardingService $backendForwardingService
    ): JsonResponse {
        $hmac = ($this->clientAuthRequestResolver ??= new ClientAuthRequestResolver(new \App\Service\Security\ApiClientAuthGuard()))->resolveOrDeny($request);

        if ($hmac instanceof JsonResponse) {
            return $hmac;
        }

        return $this->forwardProcessWithHmac($request, $backendForwardingService, $this->logger, ProcessKey::API_NFC_USERS, $hmac);
    }

    /*
    * API endpoint used by the Desktop Application to decrypt NFC card data read from the card.
    * The decrypted NFC data will be generated as a QR code on the Desktop Application.
    */
    #[ClientAuthRequired]
    #[Route('/api/nfc/decrypt', name: ProcessKey::API_NFC_DECRYPT, methods: "POST")]
    public function decryptNfcCardData(
        Request $request,
        BackendForwardingService $backendForwardingService
    ): JsonResponse {
        $hmac = ($this->clientAuthRequestResolver ??= new ClientAuthRequestResolver(new \App\Service\Security\ApiClientAuthGuard()))->resolveOrDeny($request);

        if ($hmac instanceof JsonResponse) {
            return $hmac;
        }

        return $this->forwardProcessWithHmac($request, $backendForwardingService, $this->logger, ProcessKey::API_NFC_DECRYPT, $hmac);
    }
}
