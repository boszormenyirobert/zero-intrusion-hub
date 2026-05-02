<?php

namespace App\Service\Device\Identity\Api;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class IdentityApiRequestMapper
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function mapRecoverySettingsPayload(Request $request): object
    {
        try {
            $decoded = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('Invalid identity API JSON payload', [
                'error' => $exception->getMessage(),
            ]);

            throw new BadRequestHttpException('Invalid JSON payload');
        }

        if (!is_object($decoded)) {
            throw new BadRequestHttpException('Invalid recovery settings payload.');
        }

        return $decoded;
    }
}
