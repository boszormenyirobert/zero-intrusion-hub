<?php

namespace App\Controller\CredentialHub\Domain\Read;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\UserRegistrationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;


/**
 * Domain read flow:
 * 1. Generate a QR code for domain read (basically sessionId generation). => State, and temporary data saved in the AuthBridge table by the API.
 * 2. Read the QR code with the mobile application => Mobile app automatically creates 2 calls
 * 3. Call the /credential or /credential/decrypted endpoint for confirmation and credential decryption.
 * -- 3.1: #[Route('/credential/decrypted') Do I have any credential for the current domain?  
 *         If so, send back my decrypted credentials
 * -- 3.2: // If the handy got the encryped credentials by the previous step, will be decrypted and posted #[Route('/credential')
 * 4. Use the /state endpoint to deliver the process status.
 *
 * Generally: The credentials retrieved from the mobile app in decrypted form are sent to the backend API for 
 * saving in a temporary table(auth_bridge) by the API encrypted form. 
 * The state endpoint is used to check the process status and retrieve the decrypted credential when ready from the temporary table(auth_bridge), 
 * and then delete it from it.
 */
#[Route('/api/credential-hub/domain/read')]
class DomainReadController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {}


    /*
     * API endpoint for QR-based domain read (called by Browser-Extension).
     * Requesting an QR-code from the API. Only initial step to get the sessionId.(API => save in Saved in the AuthBridge table)
     * This request uses UserRegistrationService->forwardRegistration, which encrypts and sends data to backend API.
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */
    #[Route('/qr-identity', name: 'domain_read_qr_identity', methods: "POST")]
    public function domainReadQrIdentity(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        $contentJson = $request->getContent();
        $process = "domain_read_qr_identity";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [$process => json_decode($contentJson)]
        );

        $content = $response->getContent();
        $decodedJson = \json_decode($content);
        
        return $this->json($decodedJson);
    }

    /*
     * API endpoint for credential read (called by Mobile App), after reading the QR code.
     * Returns encrypted credential data and extension auth header.
     * 
     * Uses UserRegistrationService->forwardRegistration, which encrypts and sends data to backend API.
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */
    #[Route('/credential/decrypted', name: 'domain_read_credential_encrypted', methods: "POST")]
    public function domainReadCredentialEncrypted(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        $contentJson = $request->getContent();

        $process = "domain_read_credential_encrypted";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [
                $process => json_decode($contentJson),
                'X-Extension-Auth' => $request->headers->get('X-Extension-Auth')
                ]
        );

        $content = $response->getContent();
        $decodedJson = \json_decode($content);
        return $this->json($decodedJson);
    }  

    /*
     * API endpoint to get the encrypted credentials from the Mobile App (called by Mobile App).
     * This endpoint forwards the encrypted user credentials and extension auth header to the backend API.
     * Uses UserRegistrationService->forwardRegistration, which encrypts and sends data to backend API.
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */    
    #[Route('/credential', name: 'domain_read_credential', methods: "POST")]
    public function domainReadCredential(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        $contentJson = $request->getContent();

        $process = "domain_read_credential";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [
                $process => json_decode($contentJson),
                'X-Extension-Auth' => $request->headers->get('X-Extension-Auth')
                ]
        );

        $content = $response->getContent();
        $decodedJson = \json_decode($content);

        return $this->json($decodedJson);
    }

 

    /*
     * API endpoint for domain read state polling (called by Browser-Extension).
     * Receives state request data and extension auth header, forwards to backend API.
     * Uses UserRegistrationService->forwardRegistration, which encrypts and sends data to backend API.
     * Tries to decode backend response as JSON and returns it; if not valid JSON, result may be null or incomplete.
     */
    #[Route('/state', name: 'domain_read_state', methods: "POST")]
    public function domainReadState(
        UserRegistrationService $userRegistrationService,
        Request $request
    ): JsonResponse {
        $contentJson = $request->getContent();
        $process = "domain_read_state";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [
                $process => $contentJson,
                'X-Extension-Auth' => $request->headers->get('X-Extension-Auth')
            ]
        );

        $content = $response->getContent();
        $decodedJson = \json_decode($content);
       
        
       return new JsonResponse($decodedJson);
    }
}
