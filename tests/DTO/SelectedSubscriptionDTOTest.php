<?php

declare(strict_types=1);

namespace App\Tests\DTO;

use App\DTO\SelectedSubscriptionDTO;
use PHPUnit\Framework\TestCase;

final class SelectedSubscriptionDTOTest extends TestCase
{
    public function testConstructorDefaultsAndToArray(): void
    {
        $dto = new SelectedSubscriptionDTO();

        self::assertNull($dto->id);
        self::assertSame('', $dto->subscription);
        self::assertSame([
            'subscription' => '',
            'id' => null,
        ], $dto->toArray());
    }

    public function testToArrayPreservesScalarIdentifier(): void
    {
        $dto = new SelectedSubscriptionDTO(12, 'premium');

        self::assertSame([
            'subscription' => 'premium',
            'id' => 12,
        ], $dto->toArray());
    }
}
