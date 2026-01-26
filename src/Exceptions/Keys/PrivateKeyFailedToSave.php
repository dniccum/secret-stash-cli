<?php

namespace Dniccum\Vaultr\Exceptions\Keys;

class PrivateKeyFailedToSave extends \RuntimeException
{
    public function __construct(
        $message = 'Failed to save private key file.',
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
