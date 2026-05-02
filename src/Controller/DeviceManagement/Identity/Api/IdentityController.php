<?php

/**
 * Identity module: describes a USER and their DEVICE.
 * Device Registration Process: starts with Mobile-Application installation and finishes with collecting user email and phone number.
 */

namespace App\Controller\DeviceManagement\Identity\Api;

use App\Service\Shared\ProcessKey;
use App\Service\Device\Identity\Api\IdentityApiRequestMapper;
use App\Service\Device\Identity\FirstSecretInstanceSettingsHandler;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\BackendForwardingService;
use App\Attribute\PublicRoute;

#[Route('/api')]
class IdentityController extends AbstractController
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
    }

    /*
    * API endpoint for the first step of device registration (called by Mobile, forwarded by ProxyApi).
    * Generates publicId, privateId, integrity secret, and credentialSecret.
    * Encrypts and saves these credentials in the database.
    */
    #[PublicRoute('Public secret genderation to new device registration')]
    #[Route('/secret/new', name: 'request-first-secret', methods: "GET")]
    public function requestFirstSecret(
        Request $request,
        BackendForwardingService $backendForwardingService,
        FirstSecretInstanceSettingsHandler $firstSecretInstanceSettingsHandler
    ): JsonResponse {
        $response = $backendForwardingService->forwardRegistration(
            [
                ProcessKey::FIRST_SECRET => 'no-data',
            ]
        );

        $decoded = $this->decodeFirstSecretResponse($response);
        $registratorUserPublicId = $decoded['privateSecret']['publicId'] ?? null;

        // Write into INSTANCE_SETTINGS when only one entity exists and initialization is enabled.
        // This is only used to retrieve the device identification public ID.
        $firstSecretInstanceSettingsHandler->handle($registratorUserPublicId);

        return $response;
    }

    /*
    * API endpoint for the second step of device registration (called by Mobile, forwarded by HUB).
    * Receives request payload containing email, phone number, privacyPolicy, and fcm_token.
    * Saves these along with privateId, publicId, and secret.
    */
    #[PublicRoute('Public recovery settings step during device registration bootstrap')]
    #[Route('/secret/recovery-settings', name: 'recovery-settings', methods: "POST")]
    public function requestRecoverySettings(
        Request $request,
        BackendForwardingService $backendForwardingService,
        IdentityApiRequestMapper $identityApiRequestMapper
    ): JsonResponse {
        return $backendForwardingService->forwardRegistration(
            [
                ProcessKey::RECOVERY_SETTINGS => $identityApiRequestMapper->mapRecoverySettingsPayload($request),
            ]
        );
    }

    private function decodeFirstSecretResponse(JsonResponse $response): array
    {
        try {
            $decoded = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger?->error('First secret backend response could not be decoded', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

}
