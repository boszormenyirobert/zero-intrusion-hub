<?php

namespace App\Service\Instance\HUB;

use App\DTO\BackendPayloadDTO;
use App\Logger\LogTrace;
use App\Service\Corporate\SubscriptionService;
use App\Service\JWT\JwtService;
use App\Service\Shared\ProcessKey;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class ExternalInstanceRegistrationHandler
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private JwtService $jwtService,
        private LoggerInterface $logger
    ) {
    }

    public function handle(FormInterface $formIdentity, Request $request): ?BackendPayloadDTO
    {
        if (!$formIdentity->isSubmitted() || !$formIdentity->isValid()) {
            return null;
        }

        $process = ProcessKey::GET_IDENTITY;
        $businessModel = 'businessPro';
        $jwtToken = $this->jwtService->extractPayloadFromRequest($request);

        $this->logger->info('Starting external HUB instance registration', [
            'process' => $process,
            'business_model' => $businessModel,
            'has_jwt_cookie' => $this->jwtService->extractTokenFromRequest($request) !== null,
        ]);

        if ($jwtToken === null || !isset($jwtToken['publicId'])) {
            $this->logger->warning('External HUB instance registration denied because JWT payload is missing', [
                'process' => $process,
                'business_model' => $businessModel,
            ]);

            return null;
        }

        $subscriptionData = $this->subscriptionService->getSubscriptionData($process, $businessModel, 'external', $jwtToken['publicId']);

        $this->logger->info('External HUB instance registration identity received', [
            'process' => $process,
            'business_model' => $businessModel,
            'public_id_hash' => isset($jwtToken['publicId']) && is_string($jwtToken['publicId']) ? LogTrace::fingerprint($jwtToken['publicId']) : null,
            'response_keys' => $subscriptionData->keys(),
        ]);

        return $subscriptionData;
    }
}
