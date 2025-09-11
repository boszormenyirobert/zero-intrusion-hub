<?php

namespace App\Controller\CredentialHub\Shared;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\UserRegistrationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;


#[Route('/api/credential-hub/shared/registration')]
class RegistrationController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * Called by Browser-Extension
     */
    #[Route('/qr-identity', name: 'shared_registration_qr_identity', methods: "POST")]
    public function sharedRegistrationQrIdentity(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        $contentJson = $request->getContent();
        $process = "shared_registration_qr_identity";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [$process => $contentJson]
        );

        $content = $response->getContent();
        $decodedJson = \json_decode($content);

        return $this->json($decodedJson);
    }

    /**
     *Called by Mobile App
     */
    #[Route('/new', name: 'shared_registration_new', methods: "POST")]
    public function sharedRegistrationNew(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        $contentJson = $request->getContent();

        $process = "shared_registration_new";

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
    #[Route('/state', name: 'shared_registration_state', methods: "POST")]
    public function sharedRegistrationState(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        $contentJson = $request->getContent();
        $process = "shared_registration_state";

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