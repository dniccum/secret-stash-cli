<?php

namespace Dniccum\SecretStash\Exceptions\Keys;

class MetaKeyFailedToSave extends \RuntimeException
{
    public function __construct(
        $message = 'Failed to save device metadata file.',
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
