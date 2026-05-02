<?php

namespace App\Service\User;

use App\DTO\CorporateIdentificationDTO;
use App\DTO\QrCodeResponseDTO;
use App\DTO\SecureRequestIdentityDTO;
use App\DTO\SecureRequestPayloadDTO;
use App\Service\Corporate\SecureRequestService;
use App\Service\Instance\InstanceSettingsService;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserQrCodeService
{
    public function __construct(
        private SecureRequestService $secureRequestService,
        private InstanceSettingsService $instanceSettingsService
    ) {
    }

    public function getQrCode(
        string $process,
        ?CorporateIdentificationDTO $corporateIdentification = null,
        ?string $userPublicId = null
    ): QrCodeResponseDTO {
        $authorizedData = $this->secureRequestService->postSecureAndDecode(
            $this->buildSecurePayload($process, $corporateIdentification, $userPublicId)
        );

        return QrCodeResponseDTO::fromArray($authorizedData);
    }

    public function getNfcUsers(
        string $process,
        ?CorporateIdentificationDTO $corporateIdentification = null,
        ?string $userPublicId = null
    ): JsonResponse {
        return $this->secureRequestService->postSecure(
            $this->buildSecurePayload($process, $corporateIdentification, $userPublicId)
        );
    }

    private function buildSecurePayload(
        string $process,
        ?CorporateIdentificationDTO $corporateIdentification = null,
        ?string $userPublicId = null
    ): array {
        $identity = $this->getPublicIdDomainHmac($corporateIdentification);
        $payload = new SecureRequestPayloadDTO(
            (string) $identity->publicId,
            $identity->hmac,
            (string) $identity->domain,
            $userPublicId
        );

        return [
            $process => $payload->toArray(),
        ];
    }

    private function getPublicIdDomainHmac(?CorporateIdentificationDTO $corporateIdentification = null): SecureRequestIdentityDTO
    {
        if ($corporateIdentification === null) {
            return new SecureRequestIdentityDTO(
                $this->instanceSettingsService->getInstancePublicId(),
                $this->instanceSettingsService->getInstanceDomain(),
                $this->secureRequestService->generateRequestIdentity(),
            );
        }

        return new SecureRequestIdentityDTO(
            $corporateIdentification->publicId,
            $corporateIdentification->domain,
            $corporateIdentification->hmac,
        );
    }
}
