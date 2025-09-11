<?php

namespace App\Controller\DeviceManagement;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\UserRegistrationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;


#[Route('/api')]
class OpenApiRecoveryController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

     // Forward request to the EasyLogin-Service
    #[Route('/secret/new', name: 'request-first-secret', methods: "GET")]
    public function requestFirstSecret(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        $process = "firstSecret";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [$process => "no-data"]
        );

        return $this->json($response);
    }

    // Forward request to the EasyLogin-Service
    #[Route('/secret/recovery-settings', name: 'recovery-settings', methods: "POST")]
    public function requestRecoverySettings(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): JsonResponse {
        $contentJson = $request->getContent();
        $process = "recoverySettings";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [$process => json_decode($contentJson)]
        );

        return $this->json($response);
    }

}
