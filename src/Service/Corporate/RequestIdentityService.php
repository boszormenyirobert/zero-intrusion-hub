<?php

namespace App\Service\Corporate;

use App\Service\Instance\InstanceSettingsService;

class RequestIdentityService
{
    public function __construct(
        private InstanceSettingsService $instanceSettingsService
    ) {
    }

    public function generate(): string
    {
        $currentTimestamp = time();
        $secret = $this->instanceSettingsService->getSecret();
        $message = $this->instanceSettingsService->getCorporateKey();

        return hash_hmac('sha256', $message . '|' . $currentTimestamp, $secret);
    }
}
