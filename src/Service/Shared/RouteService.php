<?php

namespace App\Service\Shared;

use Psr\Log\LoggerInterface;

class RouteService
{
    public function __construct(
        private LoggerInterface $logger,
        private ProcessRouteRegistry $processRouteRegistry,
        private string $zeroIntrusionDomain
    ) {
    }

    public function mapRoute(array $dataIntegrity): string
    {
        $key = array_key_first($dataIntegrity);

        if (!is_string($key) || $key === '') {
            $this->logger->warning('Unable to resolve backend route because no process key was provided.', [
                'payload_keys' => array_keys($dataIntegrity),
            ]);

            return '';
        }

        $target = $this->processRouteRegistry->resolve($key);

        if ($target === null) {
            $this->logger->warning('Unable to resolve backend route because process key is unknown.', [
                'process_key' => $key,
            ]);

            return '';
        }

        return $target->toUrl($this->zeroIntrusionDomain);
    }
}
