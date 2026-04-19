<?php

/**
 * Identity module: describes a USER and their DEVICE.
 * Device Registration Process: starts with Mobile-Application installation and finishes with collecting user email and phone number.
 */
namespace App\Controller\DeviceManagement\Identity\Api;

use App\Service\Device\Identity\FirstSecretInstanceSettingsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\UserRegistrationService;
use Symfony\Component\HttpFoundation\JsonResponse;


#[Route('/api')]
class IdentityController extends AbstractController
{
    /*
    * API endpoint for the first step of device registration (called by Mobile, forwarded by ProxyApi).
    * Generates publicId, privateId, integrity secret, and credentialSecret.
    * Encrypts and saves these credentials in the database.
    */    
    #[Route('/secret/new', name: 'request-first-secret', methods: "GET")]
    public function requestFirstSecret(
        UserRegistrationService $userRegistrationService,
        FirstSecretInstanceSettingsHandler $firstSecretInstanceSettingsHandler
    ): JsonResponse {
        $process = "firstSecret";

        /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [$process => "no-data"]
        );

        $decoded = json_decode($response->getContent(), true);
        $registratorUserPublicId = $decoded['privateSecret']['publicId'] ?? null;

        // Write in the INSTANCE_SETTINGS table if there is only one entity and the initialization state true
        // The is only to retrive the device identification PUBLIC_ID
        $firstSecretInstanceSettingsHandler->handle($registratorUserPublicId);

        return $this->json($response);
    }

    /*
    * API endpoint for the second step of device registration (called by Mobile, forwarded by HUB).
    * Receives request payload containing email, phone number, privacyPolicy, and fcm_token.
    * Saves these along with privateId, publicId, and secret.
    */    
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
