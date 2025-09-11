<?php

namespace App\Controller\CredentialHub\Vault\Edit;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\UserRegistrationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;


#[Route('/api/credential-hub/vault/edit')]
class VaultEditController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    /*
     * Called by Browser-Extension
    */    
    #[Route('/qr-identity', name: 'vault_edit_qr_identity', methods: "POST")]
    public function vaultEditQrIdentity(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        $contentJson = $request->getContent();
        $process = "vault_edit_qr_identity";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [$process => $contentJson]
        );
        
        $content = $response->getContent();
        $decodedJson = \json_decode($content);

        return $this->json($decodedJson);
    }

    /*
     * Called by Mobile App
    */
    #[Route('/credential', name: 'vault_edit_credential', methods: "POST")]
    public function vaultEditCredential(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        $contentJson = $request->getContent();
        $process = "vault_edit_credential";

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
    #[Route('/state', name: 'vault_edit_state', methods: "POST")]
    public function vaultDeleteState(
        UserRegistrationService $userRegistrationService,
        Request $request
    ): JsonResponse {
        $contentJson = $request->getContent();
        $process = "vault_edit_state";

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
