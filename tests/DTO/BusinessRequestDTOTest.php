<?php

declare(strict_types=1);

namespace App\Tests\DTO;

use App\DTO\BusinessRequestDTO;
use PHPUnit\Framework\TestCase;

final class BusinessRequestDTOTest extends TestCase
{
    public function testAllowedBusinessModelsContainsExpectedValues(): void
    {
        self::assertSame([
            'pswManager',
            'biometric',
            'businessBasic',
            'businessPlus',
            'businessPro',
        ], BusinessRequestDTO::allowedBusinessModels());
    }

    public function testIsAllowedBusinessModelRecognizesAllowedAndRejectedValues(): void
    {
        self::assertTrue(BusinessRequestDTO::isAllowedBusinessModel('businessPlus'));
        self::assertFalse(BusinessRequestDTO::isAllowedBusinessModel('enterpriseRoot'));
    }
}