<?php

declare(strict_types=1);

namespace App\Tests\Exception;

use App\Exception\CorporateRegistrationException;
use App\Exception\InvalidHmacException;
use PHPUnit\Framework\TestCase;

final class ExceptionCoverageTest extends TestCase
{
    public function testCorporateRegistrationExceptionStoresMetadata(): void
    {
        $previous = new \RuntimeException('previous');
        $exception = new CorporateRegistrationException('registration failed', ['field' => 'email'], 422, $previous);

        self::assertSame('registration failed', $exception->getMessage());
        self::assertSame(422, $exception->getCode());
        self::assertSame(['field' => 'email'], $exception->getErrorData());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testInvalidHmacExceptionBuildsUnauthorizedHttpException(): void
    {
        $previous = new \RuntimeException('signature mismatch');
        $exception = new InvalidHmacException('Invalid signature', $previous);

        self::assertSame('Invalid signature', $exception->getMessage());
        self::assertSame(401, $exception->getStatusCode());
        self::assertSame(['WWW-Authenticate' => 'HMAC'], $exception->getHeaders());
        self::assertSame($previous, $exception->getPrevious());
    }
}
