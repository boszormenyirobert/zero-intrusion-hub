<?php

declare(strict_types=1);

namespace App\Tests\DTO;

use App\DTO\CorporateDataDTO;
use PHPUnit\Framework\TestCase;

final class CorporateDataDTOTest extends TestCase
{
    public function testFromArrayNormalizesMissingValuesToStrings(): void
    {
        $dto = CorporateDataDTO::fromArray([
            'domain' => 'https://example.test',
            'callbackUserLogin' => 123,
            'callbackUserRegistration' => null,
            'corporateId' => true,
        ]);

        self::assertSame([
            'domain' => 'https://example.test',
            'callbackUserLogin' => '123',
            'callbackUserRegistration' => '',
            'corporateId' => '1',
        ], $dto->toArray());
    }
}
