<?php

namespace App\Controller\CredentialHub\Vault\Read;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\UserRegistrationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;


#[Route('/api/credential-hub/vault/read')]
class VaultReadController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * Called by Browser-Extension
     */
    #[Route('/qr-identity', name: 'vault_read_qr_identity', methods: "POST")]
    public function vaultReadQrIdentity(
        UserRegistrationService $userRegistrationService,
        Request $request
    ): JsonResponse {
        $contentJson = $request->getContent();
        $process = "vault_read_qr_identity";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [$process => json_decode($contentJson)]
        );

        $content = $response->getContent();
        $decodedJson = \json_decode($content);

        return $this->json($decodedJson);
    }

    /**
     * Called by Mobile App
     */
    #[Route('/credential/decrypted', name: 'vault_read_credential_encrypted', methods: "POST")]
    public function vaultReadCredentialEncrypted(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        $contentJson = $request->getContent();

        $process = "vault_read_credential_encrypted";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [
                $process => json_decode($contentJson),
                'X-Extension-Auth' => $request->headers->get('X-Extension-Auth')
                ]
        );

        $content = $response->getContent();
        $decodedJson = \json_decode($content);
        $this->logger->critical('Domain Read Credential Encrypted Response', ['response' => $decodedJson]);
        return $this->json($decodedJson);
    }

    /**
     * Called by Mobile App
     */   
    #[Route('/credential', name: 'vault_read_credential', methods: "POST")]
    public function vaultReadCredential(
        UserRegistrationService $userRegistrationService,
        Request $request
    ): JsonResponse {
        $contentJson = $request->getContent();
        $process = "vault_read_credential";        

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [
                $process => $contentJson,
                'X-Extension-Auth' => $request->headers->get('X-Extension-Auth')
            ]
        );

        $content = $response->getContent();
        $decodedJson = \json_decode($content);

        return $this->json($decodedJson);
    }    

    /**
     * Called by Browser-Extension
     */
    #[Route('/state', name: 'vault_read_state', methods: "POST")]
    public function vaultReadState(
        UserRegistrationService $userRegistrationService,
        Request $request
    ): JsonResponse {
        $contentJson = $request->getContent();
        $process = "vault_read_state";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [
                $process => $contentJson,
                'X-Extension-Auth' => $request->headers->get('X-Extension-Auth')
            ]
        );

        $content = $response->getContent();
        $decodedJson = \json_decode($content);

        return $this->json($decodedJson);
    }    
}
