<?php

namespace App\Service\Instance\HUB;

use App\Service\Corporate\SubscriptionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;

class InstanceRegistrationFollowUpHandler
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private LoggerInterface $logger
    ) {}

    public function handle(FormInterface $formSystemRegistration): bool
    {
        if (!$formSystemRegistration->isSubmitted() || !$formSystemRegistration->isValid()) {
            return false;
        }

        $userInputs = $formSystemRegistration->getData();

        $this->logger->info('Starting HUB instance registration follow-up', [
            'input_keys' => is_array($userInputs) ? array_keys($userInputs) : [],
        ]);

        $this->subscriptionService->updateOwnClient($userInputs);
        $this->subscriptionService->finalizeSubscription($userInputs);

        $this->logger->info('HUB instance registration follow-up finalized', [
            'input_keys' => is_array($userInputs) ? array_keys($userInputs) : [],
        ]);

        return true;
    }
}