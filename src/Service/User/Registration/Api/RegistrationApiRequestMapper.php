<?php

namespace App\Service\User\Registration\Api;

use App\DTO\CorporateIdentificationDTO;
use App\DTO\RegistrationProcessDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RegistrationApiRequestMapper
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function mapRegistrationRequest(Request $request, string $hmac): CorporateIdentificationDTO
    {
        $dto = CorporateIdentificationDTO::fromArray($this->decodeRequestContent($request));
        $dto->hmac = $hmac;

        return $dto;
    }

    public function mapCallbackRequest(Request $request): RegistrationProcessDTO
    {
        return $this->mapCallbackPayload($this->decodeRequestContent($request));
    }

    public function mapCallbackPayload(array $decoded): RegistrationProcessDTO
    {
        if (!isset($decoded['signature'], $decoded['publicId'], $decoded['email'])) {
            throw new BadRequestHttpException('Invalid registration callback payload.');
        }

        return RegistrationProcessDTO::mapFromArrayRegistration($decoded);
    }

    public function decodeCallbackPayload(Request $request): array
    {
        return $this->decodeRequestContent($request);
    }

    private function decodeRequestContent(Request $request): array
    {
        try {
            $decoded = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            $this->logger->error('Invalid registration API JSON payload', [
                'error' => $exception->getMessage(),
            ]);

            throw new BadRequestHttpException('Invalid JSON payload');
        }

        return is_array($decoded) ? $decoded : [];
    }
}
