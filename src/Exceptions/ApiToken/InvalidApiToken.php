<?php

namespace Dniccum\SecretStash\Exceptions\ApiToken;

class InvalidApiToken extends \RuntimeException
{
    public function __construct(
        $message = 'The provided API token is invalid. Please check your SECRET_STASH_API_TOKEN in your .env file.',
        protected $code = 403,
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
