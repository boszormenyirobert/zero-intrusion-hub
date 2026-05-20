<?php

namespace App\Controller\CredentialHub\Vault\Read;

use App\Attribute\ExtensionAuthRequired;
use App\Attribute\PublicRoute;
use App\Controller\Shared\AbstractBackendForwardingController;
use App\Service\Shared\ProcessKey;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\BackendForwardingService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Service\CredentialHub\SharedSSE;

/**
 * Vault read flow:
 * 1. Generate a QR code for domain read (basically sessionId generation). => State, and temporary data saved in the AuthBridge table by the API.
 * 2. Read the QR code with the mobile application => Mobile app automatically creates 2 calls
 * 3. Call the /credential or /credential/decrypted endpoint for confirmation and credential decryption.
 * -- 3.1: #[Route('/credential/decrypted')] Checks whether credentials exist for the current domain.
 *         If so, send back my decrypted credentials
 * -- 3.2: // If the device received the encrypted credentials in the previous step, they are decrypted and posted to #[Route('/credential')]
 * 4. Use the /state endpoint to deliver the process status.
 *
 * Generally: The credentials retrieved from the mobile app in decrypted form are sent to the backend API for
 * saving in Redis cache by the API encrypted form.
 * The state endpoint is used to check the process status and retrieve the decrypted credential when ready from Redis cache,
 * and then delete it from it.
 */
#[Route('/api/credential-hub/vault/read')]
class VaultReadController extends AbstractBackendForwardingController
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /*
     * API endpoint for QR-based vault read (called by Browser-Extension).
     * Receives QR identity data, forwards it to the Browser-Extension. Only initial step to get the sessionId.
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */
    #[PublicRoute('Public Credential Hub bootstrap route that creates the vault-read process and returns the initial QR/session payload.')]
    #[Route('/qr-identity', name: ProcessKey::VAULT_READ_QR_IDENTITY, methods: "POST")]
    public function vaultReadQrIdentity(
        BackendForwardingService $backendForwardingService,
        Request $request
    ): JsonResponse {
        return $this->forwardProcessRequest($request, $backendForwardingService, $this->logger, ProcessKey::VAULT_READ_QR_IDENTITY);
    }

    /*
     * API endpoint for credential read (called by Mobile App), after reading the QR code.
     * Returns encrypted credential data and extension auth header.
     *
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */
    #[ExtensionAuthRequired('Vault-read credential bootstrap continuation must include the X-Extension-Auth header established by the bootstrap step.')]
    #[Route('/credential/decrypted', name: ProcessKey::VAULT_READ_CREDENTIAL_ENCRYPTED, methods: "POST")]
    public function vaultReadCredentialEncrypted(
        Request $request,
        BackendForwardingService $backendForwardingService
    ): JsonResponse {
        return $this->forwardProcessRequest($request, $backendForwardingService, $this->logger, ProcessKey::VAULT_READ_CREDENTIAL_ENCRYPTED);
    }

    /*
     * API endpoint to get the encrypted credentials from the Mobile App (called by Mobile App).
     * This endpoint forwards the encrypted user credentials and extension auth header to the backend API.
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */
    #[ExtensionAuthRequired('Vault-read credential submission must include the X-Extension-Auth header established by the bootstrap step.')]
    #[Route('/credential', name: ProcessKey::VAULT_READ_CREDENTIAL, methods: "POST")]
    public function vaultReadCredential(
        BackendForwardingService $backendForwardingService,
        Request $request
    ): JsonResponse {
        return $this->forwardProcessRequest($request, $backendForwardingService, $this->logger, ProcessKey::VAULT_READ_CREDENTIAL);
    }

    /*
     * API endpoint for vault read state polling (called by Browser-Extension).
     * Receives state request data and extension auth header, forwards to backend API.
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */
    #[ExtensionAuthRequired('Vault-read state polling must include the X-Extension-Auth header established by the bootstrap step.')]
    #[Route('/state', name: ProcessKey::VAULT_READ_STATE, methods: "POST")]
    public function vaultReadState(
        BackendForwardingService $backendForwardingService,
        Request $request
    ): JsonResponse {
        return $this->forwardProcessRequest($request, $backendForwardingService, $this->logger, ProcessKey::VAULT_READ_STATE);
    }

    #[Route('/approval-challange/{key}', methods: ['GET'])]
    public function proxySse(
        string $key,
        SharedSSE $sharedSSE
    ): StreamedResponse {
        return $this->forwardProcessSSE($key, $sharedSSE);
    }
}
