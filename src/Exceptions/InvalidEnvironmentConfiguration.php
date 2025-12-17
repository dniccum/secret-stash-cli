<?php

namespace Dniccum\Vaultr\Exceptions;

class InvalidEnvironmentConfiguration extends \Exception
{
    public function __construct(?string $message = null)
    {
       parent::__construct($message ?? 'Your environment is not configured correctly. Please check your .env file.');
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return $this->message;
    }
}
