<?php

namespace App\Exception;

class CorporateRegistrationException extends \Exception
{
    private array $errorData;

    public function __construct(string $message, array $errorData = [], int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorData = $errorData;
    }

    public function getErrorData(): array
    {
        return $this->errorData;
    }
}