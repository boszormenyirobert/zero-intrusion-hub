<?php

namespace App\Controller\CredentialHub\Vault\Read;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\UserRegistrationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;
use App\Controller\CredentialHub\BackendForwared;

/**
 * Vault read flow:
 * 1. Generate a QR code for domain read (basically sessionId generation). => State, and temporary data saved in the AuthBridge table by the API.
 * 2. Read the QR code with the mobile application => Mobile app automatically creates 2 calls
 * 3. Call the /credential or /credential/decrypted endpoint for confirmation and credential decryption.
 * -- 3.1: #[Route('/credential/decrypted') Do I have any credential for the current domain?  
 *         If so, send back my decrypted credentials
 * -- 3.2: // If the handy got the encryped credentials by the previous step, will be decrypted and posted #[Route('/credential')
 * 4. Use the /state endpoint to deliver the process status.
 *
 * Generally: The credentials retrieved from the mobile app in decrypted form are sent to the backend API for 
 * saving in Redis cache by the API encrypted form. 
 * The state endpoint is used to check the process status and retrieve the decrypted credential when ready from Redis cache, 
 * and then delete it from it.
 */
#[Route('/api/credential-hub/vault/read')]
class VaultReadController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    /*
     * API endpoint for QR-based vault read (called by Browser-Extension).
     * Receives QR identity data, forwards it to the Browser-Extension. Only initial step to get the sessionId.
     * Uses UserRegistrationService->forwardRegistration, which encrypts and sends data to backend API.
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */
    #[Route('/qr-identity', name: 'vault_read_qr_identity', methods: "POST")]
    public function vaultReadQrIdentity(
        UserRegistrationService $userRegistrationService,
        Request $request
    ): JsonResponse {
        return BackendForwared::forward($request, $userRegistrationService, $this->logger, 'vault_read_qr_identity', false, true);
    }

    /*
     * API endpoint for credential read (called by Mobile App), after reading the QR code.
     * Returns encrypted credential data and extension auth header.
     *
     * Uses UserRegistrationService->forwardRegistration, which encrypts and sends data to backend API.
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */
    #[Route('/credential/decrypted', name: 'vault_read_credential_encrypted', methods: "POST")]
    public function vaultReadCredentialEncrypted(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        return BackendForwared::forward($request, $userRegistrationService, $this->logger, 'vault_read_credential_encrypted',true, true);
    }

    /*
     * API endpoint to get the encrypted credentials from the Mobile App (called by Mobile App).
     * This endpoint forwards the encrypted user credentials and extension auth header to the backend API.
     * Uses UserRegistrationService->forwardRegistration, which encrypts and sends data to backend API.
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */  
    #[Route('/credential', name: 'vault_read_credential', methods: "POST")]
    public function vaultReadCredential(
        UserRegistrationService $userRegistrationService,
        Request $request
    ): JsonResponse {
        return BackendForwared::forward($request, $userRegistrationService, $this->logger, 'vault_read_credential',true);
    }    

    /*
     * API endpoint for vault read state polling (called by Browser-Extension).
     * Receives state request data and extension auth header, forwards to backend API.
     * Uses UserRegistrationService->forwardRegistration, which encrypts and sends data to backend API.
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */
    #[Route('/state', name: 'vault_read_state', methods: "POST")]
    public function vaultReadState(
        UserRegistrationService $userRegistrationService,
        Request $request
    ): JsonResponse {
        return BackendForwared::forward($request, $userRegistrationService, $this->logger, 'vault_read_state',true);
    }    
}
