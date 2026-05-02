<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Service\Corporate\SecureRequestService;
use App\Service\User\BackendForwardingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class BackendForwardingServiceTest extends TestCase
{
    public function testForwardRegistrationDelegatesToSecureRequestService(): void
    {
        $secureRequestService = $this->createMock(SecureRequestService::class);
        $response = new JsonResponse(['status' => 'ok']);

        $secureRequestService
            ->expects(self::once())
            ->method('postSecure')
            ->with(['registration' => ['payload' => true]])
            ->willReturn($response);

        $service = new BackendForwardingService($secureRequestService);

        self::assertSame($response, $service->forwardRegistration(['registration' => ['payload' => true]]));
    }
}
