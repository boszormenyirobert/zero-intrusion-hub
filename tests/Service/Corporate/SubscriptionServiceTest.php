<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\DTO\AuthorizedCorporateIdentityDTO;
use App\DTO\CorporateDataDTO;
use App\DTO\BackendPayloadDTO;
use App\Service\Corporate\DatabaseService;
use App\Service\Corporate\SecureRequestService;
use App\Service\Corporate\SubscriptionService;
use App\Service\Shared\ProcessKey;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class SubscriptionServiceTest extends TestCase
{
    public function testGetSubscriptionDataReturnsAuthorizedPayloadDto(): void
    {
        $secureRequestService = $this->createMock(SecureRequestService::class);
        $secureRequestService
            ->expects(self::once())
            ->method('postSecureAndDecode')
            ->with([
                ProcessKey::BUSINESS_CREATE => json_encode([
                    'businessModel' => 'businessPro',
                    'publicId' => 'public-id',
                    'scope' => 'external',
                ]),
            ])
            ->willReturn([
                'service_auth_data' => ['token' => 'abc'],
            ]);

        $databaseService = $this->createMock(DatabaseService::class);
        $databaseService->expects(self::never())->method('createOwnClient');

        $service = new SubscriptionService(
            $this->createMock(LoggerInterface::class),
            $secureRequestService,
            $this->createMock(RequestStack::class),
            $databaseService
        );

        $result = $service->getSubscriptionData(ProcessKey::BUSINESS_CREATE, 'businessPro', 'external', 'public-id');

        self::assertInstanceOf(BackendPayloadDTO::class, $result);
        self::assertSame(['token' => 'abc'], $result->get('service_auth_data'));
    }

    public function testGetSubscriptionDataCreatesOwnClientForInternalScope(): void
    {
        $authorizedPayload = [
            'corporate_id' => 'corp-id',
            'corporate_id_key' => 'corp-key',
            'corporate_id_secret' => 'corp-secret',
            'ssl_public_key' => 'ssl-key',
        ];

        $secureRequestService = $this->createMock(SecureRequestService::class);
        $secureRequestService
            ->expects(self::once())
            ->method('postSecureAndDecode')
            ->willReturn($authorizedPayload);

        $databaseService = $this->createMock(DatabaseService::class);
        $databaseService
            ->expects(self::once())
            ->method('createOwnClient')
            ->with(self::callback(static function (mixed $dto) use ($authorizedPayload): bool {
                return $dto instanceof AuthorizedCorporateIdentityDTO
                    && $dto->toArray() === $authorizedPayload;
            }));

        $service = new SubscriptionService(
            $this->createMock(LoggerInterface::class),
            $secureRequestService,
            $this->createMock(RequestStack::class),
            $databaseService
        );

        $result = $service->getSubscriptionData(ProcessKey::BUSINESS_CREATE, 'businessPro', 'internal', 'public-id');

        self::assertSame($authorizedPayload, $result->toArray());
    }

    public function testFinalizeSubscriptionUsesUpdateIdentityProcessKey(): void
    {
        $corporateData = CorporateDataDTO::fromArray([
            'domain' => 'https://example.test',
            'callbackUserLogin' => 'https://example.test/api/user-login/callback',
            'callbackUserRegistration' => 'https://example.test/api/registration/callback',
            'corporateId' => 'corporate-id',
        ]);

        $secureRequestService = $this->createMock(SecureRequestService::class);
        $response = new JsonResponse(['status' => 'ok']);

        $secureRequestService
            ->expects(self::once())
            ->method('postSecure')
            ->with([
                ProcessKey::UPDATE_IDENTITY => [
                    'domain' => 'https://example.test',
                    'callbackUserLogin' => 'https://example.test/api/user-login/callback',
                    'callbackUserRegistration' => 'https://example.test/api/registration/callback',
                    'corporateId' => 'corporate-id',
                ],
            ])
            ->willReturn($response);

        $service = new SubscriptionService(
            $this->createMock(LoggerInterface::class),
            $secureRequestService,
            $this->createMock(RequestStack::class),
            $this->createMock(DatabaseService::class)
        );

        self::assertSame($response, $service->finalizeSubscription($corporateData));
    }

    public function testUpdateOwnClientDelegatesToDatabaseService(): void
    {
        $corporateData = CorporateDataDTO::fromArray([
            'domain' => 'https://example.test',
        ]);

        $databaseService = $this->createMock(DatabaseService::class);
        $databaseService->expects(self::once())->method('updateOwnClient')->with($corporateData);

        $service = new SubscriptionService(
            $this->createMock(LoggerInterface::class),
            $this->createMock(SecureRequestService::class),
            $this->createMock(RequestStack::class),
            $databaseService
        );

        $service->updateOwnClient($corporateData);
    }

    public function testGetServiceAuthDataReturnsSessionDataAndClearsIt(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())->method('get')->with('authorizedData')->willReturn(['token' => 'abc']);
        $session->expects(self::once())->method('remove')->with('authorizedData');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects(self::once())->method('getSession')->willReturn($session);

        $service = new SubscriptionService(
            $this->createMock(LoggerInterface::class),
            $this->createMock(SecureRequestService::class),
            $requestStack,
            $this->createMock(DatabaseService::class)
        );

        self::assertSame(['token' => 'abc'], $service->getServiceAuthData());
    }
}
