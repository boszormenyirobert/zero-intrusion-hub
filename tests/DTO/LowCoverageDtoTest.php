<?php

declare(strict_types=1);

namespace App\Tests\DTO;

use App\DTO\AuthenticatedUserDTO;
use App\DTO\BusinessRequestDTO;
use App\DTO\BusinessSubscriptionDataDTO;
use App\DTO\CorporateIdentificationDTO;
use App\DTO\JwtContextDTO;
use App\DTO\RejectedProcessStateDTO;
use App\DTO\ReplaceDeviceResultDTO;
use PHPUnit\Framework\TestCase;

final class LowCoverageDtoTest extends TestCase
{
    public function testBusinessRequestDtoConstructorAndToArray(): void
    {
        $dto = new BusinessRequestDTO('businessPro');

        self::assertSame(['businessModel' => 'businessPro'], $dto->toArray());
    }

    public function testBusinessSubscriptionDataDtoMapsArraysAndFallbacks(): void
    {
        $dto = BusinessSubscriptionDataDTO::fromArray([
            'accounts' => ['account-1'],
            'businessSubscription' => ['plan' => 'pro'],
        ]);

        self::assertSame([
            'accounts' => ['account-1'],
            'businessSubscription' => ['plan' => 'pro'],
        ], $dto->toArray());

        $fallbackDto = BusinessSubscriptionDataDTO::fromArray([
            'accounts' => 'invalid',
            'businessSubscription' => null,
        ]);

        self::assertSame(['accounts' => [], 'businessSubscription' => []], $fallbackDto->toArray());
    }

    public function testCorporateIdentificationDtoMapsAndExportsFields(): void
    {
        $dto = CorporateIdentificationDTO::fromArray([
            'publicId' => 123,
            'domain' => 'example.test',
            'hmac' => 'hmac-value',
            'userPublicId' => 'user-public-id',
        ]);

        self::assertSame([
            'publicId' => '123',
            'domain' => 'example.test',
            'hmac' => 'hmac-value',
            'userPublicId' => 'user-public-id',
        ], $dto->toArray());
    }

    public function testJwtContextDtoSupportsInvalidAndUserConversion(): void
    {
        $invalid = JwtContextDTO::invalid();
        self::assertFalse($invalid->isJwtValid);
        self::assertSame('', $invalid->userPublicId);
        self::assertNull($invalid->payload);

        $dto = new JwtContextDTO(true, 'public-id', 'user@example.test', ['username' => 'user@example.test']);
        self::assertSame([
            'isJwtValid' => true,
            'userPublicId' => 'public-id',
            'userEmail' => 'user@example.test',
            'payload' => ['username' => 'user@example.test'],
        ], $dto->toArray());

        $userDto = $dto->toUserDto();
        self::assertInstanceOf(AuthenticatedUserDTO::class, $userDto);
        self::assertSame('public-id', $userDto->publicId);
        self::assertSame('user@example.test', $userDto->email);
    }

    public function testRejectedProcessStateDtoExportsStatusAndReason(): void
    {
        $dto = new RejectedProcessStateDTO('rejected', 'login_rejected_whitelist');

        self::assertSame([
            'status' => 'rejected',
            'reason' => 'login_rejected_whitelist',
        ], $dto->toArray());
    }

    public function testAuthenticatedUserDtoSupportsArrayConversions(): void
    {
        $dto = AuthenticatedUserDTO::fromArray([
            'userPublicId' => 'public-1',
            'userEmail' => 'user@example.com',
        ]);

        self::assertSame([
            'publicId' => 'public-1',
            'email' => 'user@example.com',
        ], $dto->toArray());

        self::assertSame([
            'userPublicId' => 'public-1',
            'userEmail' => 'user@example.com',
        ], $dto->toTemplateArray());
    }

    public function testReplaceDeviceResultDtoSupportsValidationAndQrPayload(): void
    {
        $dto = ReplaceDeviceResultDTO::fromArray([
            'publicId' => 'public-1',
            'privateId' => 'private-1',
            'secret' => 'secret-1',
        ]);

        self::assertTrue($dto->isValid());
        self::assertSame([
            'publicId' => 'public-1',
            'privateId' => 'private-1',
            'secret' => 'secret-1',
        ], $dto->toArray());

        self::assertSame([
            'publicId' => 'public-1',
            'privateId' => 'private-1',
            'secret' => 'secret-1',
            'type' => 'recovery',
            'source' => 'easyPublic',
        ], $dto->toQrPayload());

        self::assertFalse(ReplaceDeviceResultDTO::fromArray([])->isValid());
    }
}
