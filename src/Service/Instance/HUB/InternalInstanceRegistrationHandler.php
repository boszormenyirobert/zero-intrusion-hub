<?php

namespace App\Service\Instance\HUB;

use App\Repository\InstanceSettingsRepository;
use App\Service\Corporate\SubscriptionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormInterface;

class InternalInstanceRegistrationHandler
{
    public function __construct(
        private InstanceSettingsRepository $instanceSettingsRepository,
        private SubscriptionService $subscriptionService,
        private LoggerInterface $logger
    ) {}

    public function handle(FormInterface $formIdentity, Request $request): ?array
    {
        if (!$formIdentity->isSubmitted() || !$formIdentity->isValid()) {
            return null;
        }

        $process = 'getIdentity';
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
            'userPublicId' => $publicId,
        ]);

        $subscriptionData = $this->subscriptionService->getSubscriptionData($process, $businessModel, 'internal', $publicId);

        $this->logger->info('Internal HUB instance registration identity received', [
            'process' => $process,
            'business_model' => $businessModel,
            'has_subscription_data' => $subscriptionData !== null,
            'response_keys' => is_array($subscriptionData) ? array_keys($subscriptionData) : [],
        ]);

        $corporateId = is_array($subscriptionData) ? ($subscriptionData['corporate_id'] ?? null) : null;

        if (!is_string($corporateId) || $corporateId === '') {
            $this->logger->error('Internal HUB instance registration corporate_id is missing.', [
                'process' => $process,
                'business_model' => $businessModel,
                'response_keys' => is_array($subscriptionData) ? array_keys($subscriptionData) : [],
            ]);

            return $subscriptionData;
        }

        $this->finalizeRegistration($corporateId, $request);

        return $subscriptionData;
    }

    public function finalizeRegistration($corporateId, Request $request): bool
    {
        $domain = rtrim($request->getSchemeAndHttpHost(), '/');
        $userInputs = [
            'domain' => $domain,
            'callbackUserLogin' => $domain . '/api/user-login/callback',
            'callbackUserRegistration' => $domain . '/api/registration/callback',
            'corporateId' => $corporateId
        ];

        $this->logger->info('Registration CorporateId', [           
            'corporateId' => $corporateId,
        ]);
        $this->subscriptionService->updateOwnClient($userInputs);
        $this->subscriptionService->finalizeSubscription($userInputs);

        $this->logger->info('HUB instance registration follow-up finalized', [
            'input_keys' => is_array($userInputs) ? array_keys($userInputs) : [],
        ]);

        return true;
    }
}