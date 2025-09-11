<?php

namespace App\Service\Device;

use App\Service\Corporate\AuthorizationControllService;

class ReplaceDeviceService
{

    public function __construct(
        private AuthorizationControllService $authorizationControllService
    ) {}

    public function forwardRegistration(array $data)
    {
        $response = $this->authorizationControllService->getSecurePostRequest(
            $data
        );

        return $response->toArray(true);
    }

    public function controllResponse($replaceDevice)
    {
        if (
            array_key_exists('publicId', $replaceDevice) &&
            array_key_exists('privateId', $replaceDevice) &&
            array_key_exists('secret', $replaceDevice)
        ) {
            return true;
        }

        return false;
    }
}
