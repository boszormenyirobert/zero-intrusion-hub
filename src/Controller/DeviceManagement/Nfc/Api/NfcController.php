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
use Symfony\Component\HttpFoundation\Response;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Service\User\UserRegistrationService;

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
            $headers =  $request->headers->all();

            $corporateIentification = json_decode($request->getContent(), true);       

            $process = "api_nfc_users"; 

            $corporateIentification['hmac'] = $headers['x-client-auth'];

            /** @var Response $response */
            $response = $userRegistrationService->forwardRegistration(
            [
                $process => $corporateIentification,
                'X-Extension-Auth' => $corporateIentification['hmac']
            ]
        );

        return $this->json($response);
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
            $headers =  $request->headers->all();

            $data = json_decode($request->getContent(), true);       

            $process = "api_nfc_decrypt"; 

            $data['hmac'] = $headers['x-client-auth'];

            /** @var Response $response */
            $response = $userRegistrationService->forwardRegistration(
            [
                $process => $data,
                'X-Extension-Auth' => $data['hmac']
            ]
        );

        return $this->json($response);
    }    
}