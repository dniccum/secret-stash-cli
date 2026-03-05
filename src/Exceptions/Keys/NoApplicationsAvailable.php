<?php

namespace Dniccum\SecretStash\Exceptions\Keys;

use Exception;

class NoApplicationsAvailable extends Exception
{
    public function __construct(string $message = 'No applications were found for the provided organization.', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
