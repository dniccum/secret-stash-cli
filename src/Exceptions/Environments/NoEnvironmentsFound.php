<?php

namespace Dniccum\SecretStash\Exceptions\Environments;

class NoEnvironmentsFound extends \RuntimeException
{
    public function __construct(
        $message = 'No environments found.',
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
