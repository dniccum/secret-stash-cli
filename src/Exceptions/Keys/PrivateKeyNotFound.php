<?php

namespace Dniccum\SecretStash\Exceptions\Keys;

class PrivateKeyNotFound extends \RuntimeException
{
    public function __construct(
        $message = 'Private key not found. Run "secret-stash:keys init" first.',
        protected $code = 400,
        protected ?\Throwable $previous = null
    ) {
        $this->message = $message;
        parent::__construct($message, $this->code, $previous);
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->message;
    }
}
