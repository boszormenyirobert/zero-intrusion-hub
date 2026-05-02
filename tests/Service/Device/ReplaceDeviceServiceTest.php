<?php

declare(strict_types=1);

namespace App\Tests\Service\Device;

use App\DTO\ReplaceDeviceResultDTO;
use App\Service\Corporate\SecureRequestService;
use App\Service\Device\ReplaceDeviceService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ReplaceDeviceServiceTest extends TestCase
{
    public function testValidateResponseReturnsTrueForValidDto(): void
    {
        $secureRequestService = $this->createMock(SecureRequestService::class);
        $service = new ReplaceDeviceService($secureRequestService, $this->createMock(LoggerInterface::class));

        $dto = ReplaceDeviceResultDTO::fromArray([
            'publicId' => 'public',
            'privateId' => 'private',
            'secret' => 'secret',
        ]);

        self::assertTrue($service->validateResponse($dto));
    }

    public function testForwardRegistrationMapsBackendResponseToDto(): void
    {
        $secureRequestService = $this->createMock(SecureRequestService::class);
        $secureRequestService
            ->expects(self::once())
            ->method('postSecure')
            ->with(['replaceDevice' => ['email' => 'a@example.test']])
            ->willReturn(new JsonResponse([
                'publicId' => 'public',
                'privateId' => 'private',
                'secret' => 'secret',
            ]));

        $service = new ReplaceDeviceService($secureRequestService, $this->createMock(LoggerInterface::class));
        $result = $service->forwardRegistration(['replaceDevice' => ['email' => 'a@example.test']]);

        self::assertSame('public', $result->publicId);
        self::assertSame('private', $result->privateId);
        self::assertSame('secret', $result->secret);
    }

    public function testForwardRegistrationLogsErrorForInvalidBackendJson(): void
    {
        $secureRequestService = $this->createMock(SecureRequestService::class);
        $secureRequestService
            ->expects(self::once())
            ->method('postSecure')
            ->willReturn(new JsonResponse('{invalid', 200, [], true));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $service = new ReplaceDeviceService($secureRequestService, $logger);
        $result = $service->forwardRegistration(['replaceDevice' => ['email' => 'a@example.test']]);

        self::assertFalse($result->isValid());
    }
}