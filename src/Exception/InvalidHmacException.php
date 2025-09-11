<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class InvalidHmacException extends UnauthorizedHttpException
{
    public function __construct(string $message = 'Invalid HMAC', \Throwable $previous = null)
    {
        parent::__construct('HMAC', $message, $previous, 401);
    }
}
