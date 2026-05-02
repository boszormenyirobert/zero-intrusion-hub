<?php

declare(strict_types=1);

namespace App\Tests\DTO;

use App\DTO\RegistrationProcessDTO;
use PHPUnit\Framework\TestCase;

final class RegistrationProcessDTOTest extends TestCase
{
    public function testMapFromArrayRegistrationCastsExpectedFields(): void
    {
        $dto = RegistrationProcessDTO::mapFromArrayRegistration([
            'signature' => 123,
            'publicId' => 456,
            'email' => 'user@example.test',
            'registrationProcessId' => 789,
        ]);

        self::assertSame('123', $dto->getSignature());
        self::assertSame('456', $dto->getPublicId());
        self::assertSame('user@example.test', $dto->getEmail());
        self::assertSame('789', $dto->getProcessId());
    }

    public function testMapFromArrayLoginReturnsTypedArrayRepresentation(): void
    {
        $dto = RegistrationProcessDTO::mapFromArrayLogin([
            'signature' => 'signature',
            'publicId' => 'public-id',
            'email' => 'user@example.test',
            'processId' => 'process-123',
        ]);

        self::assertSame([
            'signature' => 'signature',
            'publicId' => 'public-id',
            'email' => 'user@example.test',
            'processId' => 'process-123',
        ], $dto->toArray());
    }
}
