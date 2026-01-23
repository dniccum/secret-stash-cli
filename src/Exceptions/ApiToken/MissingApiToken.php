<?php

namespace Dniccum\Vaultr\Exceptions\ApiToken;

class MissingApiToken extends \RuntimeException
{
    public function __construct(
        $message = 'API token is not configured. Please set VAULTR_API_TOKEN in your .env file.',
        protected $code = 400,
        protected ?\Throwable $previous = null
    )
    {
        $this->message = $message;
        parent::__construct($message, $this->code, $previous);
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->message;
    }
}
