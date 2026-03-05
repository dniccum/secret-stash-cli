<?php

namespace Dniccum\SecretStash\Exceptions\Keys;

class DeviceKeyNotRegistered extends \RuntimeException
{
    public function __construct(
        $message = 'Device key not registered. Run "secret-stash:keys init" first.',
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
