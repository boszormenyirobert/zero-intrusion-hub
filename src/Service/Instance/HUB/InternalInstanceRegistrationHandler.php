<?php

namespace App\Service\Instance\HUB;

use App\DTO\BackendPayloadDTO;
use App\DTO\CorporateDataDTO;
use App\Logger\LogTrace;
use App\Repository\InstanceSettingsRepository;
use App\Service\Corporate\SubscriptionService;
use App\Service\Shared\ProcessKey;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormInterface;

class InternalInstanceRegistrationHandler
{
    public function __construct(
        private InstanceSettingsRepository $instanceSettingsRepository,
        private SubscriptionService $subscriptionService,
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
        $publicId = null;

        $instanceSettings = $this->instanceSettingsRepository->findCurrentSettings();

        if ($instanceSettings) {
            $publicId = $instanceSettings->getPublicId();
        } else {
            $this->logger->error('Instance settings not initialized.', [
                'process' => $process,
                'business_model' => $businessModel,
            ]);

            return null;
        }

        if (!is_string($publicId) || $publicId === '') {
            $this->logger->error('Instance settings publicId is missing.', [
                'process' => $process,
                'business_model' => $businessModel,
                'instance_settings_id' => $instanceSettings->getId(),
            ]);

            return null;
        }

        $this->logger->info('Starting internal HUB instance registration', [
            'process' => $process,
            'business_model' => $businessModel,
            'user_public_id_hash' => LogTrace::fingerprint($publicId),
        ]);

        $subscriptionData = $this->subscriptionService->getSubscriptionData($process, $businessModel, 'internal', $publicId);

        $this->logger->info('Internal HUB instance registration identity received', [
            'process' => $process,
            'business_model' => $businessModel,
            'has_subscription_data' => $subscriptionData !== null,
            'response_keys' => $subscriptionData?->keys() ?? [],
        ]);

        $corporateId = $subscriptionData?->get('corporate_id');

        if (!is_string($corporateId) || $corporateId === '') {
            $this->logger->error('Internal HUB instance registration corporate_id is missing.', [
                'process' => $process,
                'business_model' => $businessModel,
                'response_keys' => $subscriptionData?->keys() ?? [],
            ]);

            return $subscriptionData;
        }

        $this->finalizeRegistration($corporateId, $request);

        return $subscriptionData;
    }

    public function finalizeRegistration(string $corporateId, Request $request): bool
    {
        $domain = rtrim($request->getSchemeAndHttpHost(), '/');
        $userInputs = CorporateDataDTO::fromArray([
            'domain' => $domain,
            'callbackUserLogin' => $domain . '/api/user-login/callback',
            'callbackUserRegistration' => $domain . '/api/registration/callback',
            'corporateId' => $corporateId,
        ]);

        $this->logger->info('Registration CorporateId', [
            'corporate_id_hash' => LogTrace::fingerprint($corporateId),
        ]);
        $this->subscriptionService->updateOwnClient($userInputs);
        $this->subscriptionService->finalizeSubscription($userInputs);

        $this->logger->info('HUB instance registration follow-up finalized', [
            'input_keys' => array_keys($userInputs->toArray()),
        ]);

        return true;
    }
}
