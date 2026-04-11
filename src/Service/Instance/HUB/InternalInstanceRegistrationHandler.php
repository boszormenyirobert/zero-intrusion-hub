<?php

namespace App\Service\Instance\HUB;

use App\Service\Corporate\SubscriptionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Form\FormInterface;

class InternalInstanceRegistrationHandler
{
    public function __construct(
        private ContainerBagInterface $params,
        private SubscriptionService $subscriptionService,
        private LoggerInterface $logger
    ) {}

    public function handle(FormInterface $formIdentity): ?array
    {
        if (!$formIdentity->isSubmitted() || !$formIdentity->isValid()) {
            return null;
        }

        $process = 'getIdentity';
        $businessModel = 'businessPro';
        $publicId = $this->params->get('INSTALLATION_PUBLIC_ID');

        $this->logger->info('Starting internal HUB instance registration', [
            'process' => $process,
            'business_model' => $businessModel,
            'public_id' => $publicId,
        ]);

        $subscriptionData = $this->subscriptionService->getSubscriptionData($process, $businessModel, 'internal', $publicId);

        $this->logger->info('Internal HUB instance registration identity received', [
            'process' => $process,
            'business_model' => $businessModel,
            'has_subscription_data' => $subscriptionData !== null,
            'response_keys' => is_array($subscriptionData) ? array_keys($subscriptionData) : [],
        ]);

        return $subscriptionData;
    }
}