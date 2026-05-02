<?php

namespace App\Service\Corporate;

use App\DTO\AuthorizedCorporateIdentityDTO;
use App\DTO\BackendPayloadDTO;
use App\DTO\CorporateDataDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Service\Shared\ProcessKey;

class SubscriptionService
{
    private const SUBSCRIPTION_SCOPE_INTERNAL = 'internal';

    public function __construct(
        private LoggerInterface $logger,
        private SecureRequestService $secureRequestService,
        private RequestStack $requestStack,
        private DatabaseService $databaseService
    ) {
    }


    /**
     * Retrieves identifier data from API.
     *
     * Authorization headers: SERVICE_API_KEY, SERVICE_API_SECRET
     * Encrypted using: DATA_HASH_SECRET
     */
    public function getSubscriptionData(string $process, string $businessModel, string $type, string $publicId): BackendPayloadDTO
    {
        $authorizedData = $this->secureRequestService->postSecureAndDecode([
            $process => $this->buildSubscriptionRequestPayload($businessModel, $type, $publicId),
        ]);

        if ($type === self::SUBSCRIPTION_SCOPE_INTERNAL) {
            $this->databaseService->createOwnClient(AuthorizedCorporateIdentityDTO::fromArray($authorizedData));
        }

        return BackendPayloadDTO::fromArray($authorizedData);
    }

    public function updateOwnClient(CorporateDataDTO $userInputs)
    {
        $this->databaseService->updateOwnClient($userInputs);
    }

    public function getServiceAuthData()
    {
        $session = $this->requestStack->getSession();
        $authorizedData = $session->get('authorizedData');
        $session->remove('authorizedData');

        return $authorizedData;
    }

    public function finalizeSubscription(CorporateDataDTO $corporateData)
    {
        $this->logger->info('Starting HUB instance registration finalization', [
            'corporateDataKeys' => array_keys($corporateData->toArray()),
        ]);
        $process = ProcessKey::UPDATE_IDENTITY;
        $response = $this->secureRequestService->postSecure(
            [$process => $corporateData->toArray()]
        );

        return $response;
    }

    private function buildSubscriptionRequestPayload(string $businessModel, string $type, string $publicId): string
    {
        return json_encode([
            'businessModel' => $businessModel,
            'publicId' => $publicId,
            'scope' => $type,
        ]);
    }
}
