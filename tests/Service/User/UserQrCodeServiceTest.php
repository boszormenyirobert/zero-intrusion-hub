<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\DTO\CorporateIdentificationDTO;
use App\Service\Corporate\SecureRequestService;
use App\Service\Instance\InstanceSettingsService;
use App\Service\Shared\ProcessKey;
use App\Service\User\UserQrCodeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class UserQrCodeServiceTest extends TestCase
{
    public function testGetQrCodeBuildsSecurePayloadFromInstanceSettings(): void
    {
        $secureRequestService = $this->createMock(SecureRequestService::class);
        $secureRequestService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->willReturn('generated-hmac');
        $secureRequestService
            ->expects(self::once())
            ->method('postSecureAndDecode')
            ->with([
                ProcessKey::USER_LOGIN => [
                    'corporatePublicId' => 'instance-public-id',
                    'corporateAuthentication' => 'generated-hmac',
                    'domain' => 'https://hub.example.test',
                    'userPublicId' => 'user-public-id',
                ],
            ])
            ->willReturn([
                'domainProcessId' => 'process-123',
                'qrCode' => 'qr-content',
            ]);

        $instanceSettingsService = $this->createMock(InstanceSettingsService::class);
        $instanceSettingsService->method('getInstancePublicId')->willReturn('instance-public-id');
        $instanceSettingsService->method('getInstanceDomain')->willReturn('https://hub.example.test');

        $service = new UserQrCodeService($secureRequestService, $instanceSettingsService);
        $response = $service->getQrCode(ProcessKey::USER_LOGIN, null, 'user-public-id');

        self::assertSame('process-123', $response->getDomainProcessId());
        self::assertSame('qr-content', $response->getQrCode());
    }

    public function testGetQrCodeUsesExplicitCorporateIdentificationWhenProvided(): void
    {
        $corporateIdentification = CorporateIdentificationDTO::fromArray([
            'publicId' => 'external-public-id',
            'domain' => 'https://external.example.test',
            'hmac' => 'provided-hmac',
            'userPublicId' => 'external-user-public-id',
        ]);

        $secureRequestService = $this->createMock(SecureRequestService::class);
        $secureRequestService
            ->expects(self::never())
            ->method('generateRequestIdentity');
        $secureRequestService
            ->expects(self::once())
            ->method('postSecureAndDecode')
            ->with([
                ProcessKey::USER_REGISTRATION => [
                    'corporatePublicId' => 'external-public-id',
                    'corporateAuthentication' => 'provided-hmac',
                    'domain' => 'https://external.example.test',
                    'userPublicId' => null,
                ],
            ])
            ->willReturn([
                'registrationProcessId' => 'registration-123',
                'qrCode' => 'registration-qr',
            ]);

        $instanceSettingsService = $this->createMock(InstanceSettingsService::class);

        $service = new UserQrCodeService($secureRequestService, $instanceSettingsService);
        $response = $service->getQrCode(ProcessKey::USER_REGISTRATION, $corporateIdentification);

        self::assertSame('registration-123', $response->getRegistrationProcessId());
        self::assertSame('registration-qr', $response->getQrCode());
    }

    public function testGetNfcUsersStillReturnsRawSecureResponse(): void
    {
        $secureRequestService = $this->createMock(SecureRequestService::class);
        $secureRequestService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->willReturn('generated-hmac');

        $rawResponse = new JsonResponse(['users' => []]);

        $secureRequestService
            ->expects(self::once())
            ->method('postSecure')
            ->with([
                ProcessKey::API_NFC_USERS => [
                    'corporatePublicId' => 'instance-public-id',
                    'corporateAuthentication' => 'generated-hmac',
                    'domain' => 'https://hub.example.test',
                    'userPublicId' => null,
                ],
            ])
            ->willReturn($rawResponse);

        $secureRequestService
            ->expects(self::never())
            ->method('postSecureAndDecode');

        $instanceSettingsService = $this->createMock(InstanceSettingsService::class);
        $instanceSettingsService->method('getInstancePublicId')->willReturn('instance-public-id');
        $instanceSettingsService->method('getInstanceDomain')->willReturn('https://hub.example.test');

        $service = new UserQrCodeService($secureRequestService, $instanceSettingsService);

        self::assertSame($rawResponse, $service->getNfcUsers(ProcessKey::API_NFC_USERS));
    }
}