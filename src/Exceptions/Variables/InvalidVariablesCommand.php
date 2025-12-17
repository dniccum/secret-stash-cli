<?php

namespace Dniccum\Vaultr\Exceptions\Variables;

class InvalidVariablesCommand extends \InvalidArgumentException
{
    public function __construct(string $action)
    {
        parent::__construct("Invalid variables command: {$action}");
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->message;
    }
}
