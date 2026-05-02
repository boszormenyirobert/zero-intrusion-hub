<?php

namespace App\Service\Instance\HUB;

use App\DTO\CorporateDataDTO;
use App\Service\Corporate\SubscriptionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;

class InstanceRegistrationFollowUpHandler
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private LoggerInterface $logger
    ) {
    }

    public function handle(FormInterface $formSystemRegistration): bool
    {
        if (!$formSystemRegistration->isSubmitted() || !$formSystemRegistration->isValid()) {
            return false;
        }

        /** @var CorporateDataDTO $userInputs */
        $userInputs = $formSystemRegistration->getData();

        $this->logger->info('Starting HUB instance registration follow-up', [
            'input_keys' => array_keys($userInputs->toArray()),
        ]);

        $this->subscriptionService->updateOwnClient($userInputs);
        $this->subscriptionService->finalizeSubscription($userInputs);

        $this->logger->info('HUB instance registration follow-up finalized', [
            'input_keys' => array_keys($userInputs->toArray()),
        ]);

        return true;
    }
}
