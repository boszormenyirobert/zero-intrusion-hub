<?php

/**
 * Handles NFC-related API endpoints for device management.
 * - Provides Desktop Application endpoints to fetch all NFC users.
 * - Handles decryption of NFC card data and prepares it for QR code generation.
 * - Forwards requests to the backend via the ForwardingService (formerly UserRegistrationService).
 * - Ensures request data integrity using client authentication headers (HMAC).
 */
namespace App\Controller\DeviceManagement\Nfc\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\User\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Service\User\UserRegistrationService;
use App\Controller\CredentialHub\BackendForwared;

class NfcController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private JWTEncoderInterface $jwtEncoder,
        private UserService $userService,
        private UserRepository $userRepository,
    ) {}

    /*
    * API endpoint used by the Desktop Application to fetch all NFC users.
    * The encrypted NFC data from the selected user will be written onto the NFC card by the Desktop Application.
    */
    #[Route('/api/nfc/users', name: 'api_nfc_users', methods: "POST")]
    public function getNfcUsers(
        Request $request,
        UserRegistrationService $userRegistrationService // Rename the UserRegistrationService => ForwardingService
        ) {
        $hmac = $request->headers->get('x-client-auth');

        return BackendForwared::forwardWithHmac($request, $userRegistrationService, $this->logger, "api_nfc_users", $hmac);        
    }

    /*
    * API endpoint used by the Desktop Application to decrypt NFC card data read from the card.
    * The decrypted NFC data will be generated as a QR code on the Desktop Application.
    */  
    #[Route('/api/nfc/decrypt', name: 'api_nfc_decrypt', methods: "POST")]
    public function NfcDecryptCardData(
        Request $request,
        UserRegistrationService $userRegistrationService // Rename the UserRegistrationService => ForwardingService
        ) {
        $hmac = $request->headers->get('x-client-auth');

        return BackendForwared::forwardWithHmac($request, $userRegistrationService, $this->logger, "api_nfc_decrypt", $hmac);           
    }    
}