<?php

namespace App\Controller\CredentialHub\Domain\Read;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\UserRegistrationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;


#[Route('/api/credential-hub/domain/read')]
class DomainReadController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {}


    /**
     * Called by Browser-Extension
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

    /**
     * Called by Browser-Extension
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

      //  return new JsonResponse($decodedJson);
    }

    /**
     * Called by Mobile App
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

}
