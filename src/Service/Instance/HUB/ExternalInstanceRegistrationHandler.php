<?php

namespace App\Service\Instance\HUB;

use App\Service\Corporate\SubscriptionService;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class ExternalInstanceRegistrationHandler
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private JWTEncoderInterface $jwtEncoder,
        private LoggerInterface $logger
    ) {}

    public function handle(FormInterface $formIdentity, Request $request): bool
    {
        if (!$formIdentity->isSubmitted() || !$formIdentity->isValid()) {
            return false;
        }

        $process = 'getIdentity';
        $businessModel = 'businessPro';
        $jwtTokenEncoded = $request->cookies->get('jwt_token') ?? '';

        $this->logger->info('Starting external HUB instance registration', [
            'process' => $process,
            'business_model' => $businessModel,
            'has_jwt_cookie' => $jwtTokenEncoded !== '',
        ]);

        $jwtToken = $this->jwtEncoder->decode($jwtTokenEncoded);

        $this->subscriptionService->getSubscriptionData($process, $businessModel, 'external', $jwtToken['publicId']);

        $this->logger->info('External HUB instance registration identity received', [
            'process' => $process,
            'business_model' => $businessModel,
            'public_id' => $jwtToken['publicId'] ?? null,
        ]);

        return true;
    }
}