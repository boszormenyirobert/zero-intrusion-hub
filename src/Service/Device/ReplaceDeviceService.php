<?php

namespace App\Service\Device;

use App\Service\Corporate\AuthorizationControllService;

/**
 * Service for handling device replacement registration and response validation.
 *
 * Forwards registration data to the backend and checks the validity of device replacement responses.
 */
class ReplaceDeviceService
{

    public function __construct(
        private AuthorizationControllService $authorizationControllService
    ) {}

    /**
     * Forwards device registration data to the backend via AuthorizationControllService.
     *
     * @param array $data Registration data to send
     * @return array Backend response as array
     */
    public function forwardRegistration(array $data): array
    {
        $response = $this->authorizationControllService->getSecurePostRequest(
            $data
        );

        try {
            return $response->toArray(true);
        } catch (\Exception $e) {            
            return [];
        }
    }

    /**
     * Validates the backend response for device replacement.
     *
     * Checks if required keys (publicId, privateId, secret) exist in the response array.
     *
     * @param array $replaceDevice Backend response array
     * @return bool True if valid, false otherwise
     */
    public function controllResponse(array $replaceDevice): bool
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
