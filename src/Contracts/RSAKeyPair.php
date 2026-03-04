<?php

namespace Dniccum\SecretStash\Contracts;

class RSAKeyPair
{
    public function __construct(public string $private_key, public string $public_key) {}
}
