<?php

namespace App\Service\User\Login\Api;

use App\DTO\CorporateIdentificationDTO;
use App\DTO\QrCodeResponseDTO;
use App\DTO\RegistrationProcessDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class LoginApiRequestMapper
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function mapLoginRequest(Request $request): CorporateIdentificationDTO
    {
        return CorporateIdentificationDTO::fromArray($this->decodeRequestContent($request));
    }

    public function mapCallbackRequest(Request $request): RegistrationProcessDTO
    {
        $decoded = $this->decodeRequestContent($request);

        if (!isset($decoded['signature'], $decoded['publicId'], $decoded['email'])) {
            throw new BadRequestHttpException('Invalid login callback payload.');
        }

        return RegistrationProcessDTO::mapFromArrayLogin($decoded);
    }

    public function mapCheckRequest(Request $request): QrCodeResponseDTO
    {
        $decoded = $this->decodeRequestContent($request);

        if (!isset($decoded['domainProcessId'])) {
            throw new BadRequestHttpException('Invalid login check payload.');
        }

        return QrCodeResponseDTO::fromArray($decoded);
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
            $this->logger->error('Invalid login API JSON payload', [
                'error' => $exception->getMessage(),
            ]);

            throw new BadRequestHttpException('Invalid JSON payload');
        }

        return is_array($decoded) ? $decoded : [];
    }
}
