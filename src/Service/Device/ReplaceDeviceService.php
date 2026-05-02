<?php

namespace App\Service\Device;

use App\DTO\ReplaceDeviceResultDTO;
use App\Service\Corporate\SecureRequestService;
use Psr\Log\LoggerInterface;

/**
 * Service for handling device replacement registration and response validation.
 *
 * Forwards registration data to the backend and checks the validity of device replacement responses.
 */
class ReplaceDeviceService
{
    public function __construct(
        private SecureRequestService $secureRequestService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Forwards device registration data to the backend via `SecureRequestService`.
     *
     * @param array $data Registration data to send
     * @return ReplaceDeviceResultDTO Backend response as DTO
     */
    public function forwardRegistration(array $data): ReplaceDeviceResultDTO
    {
        $response = $this->secureRequestService->postSecure(
            $data
        );

        try {
            $decoded = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

            return ReplaceDeviceResultDTO::fromArray(is_array($decoded) ? $decoded : []);
        } catch (\Exception $e) {
            $this->logger->error('Replace device backend response could not be decoded', [
                'error' => $e->getMessage(),
            ]);

            return new ReplaceDeviceResultDTO();
        }
    }

    /**
     * Validates the backend response for device replacement.
     *
     * Checks whether the required fields are present in the backend response.
     *
     * @param ReplaceDeviceResultDTO $replaceDevice Backend response DTO
     * @return bool True if valid, false otherwise
     */
    public function validateResponse(ReplaceDeviceResultDTO $replaceDevice): bool
    {
        return $replaceDevice->isValid();
    }
}
