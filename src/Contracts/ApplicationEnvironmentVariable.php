<?php

namespace Dniccum\SecretStash\Contracts;

use Carbon\Carbon;

class ApplicationEnvironmentVariable
{
    public function __construct(
        public string $id,
        public string $name,
        public ?array $payload = null,
        public ?string $created_at = null
    ) {
        $this->created_at = Carbon::parse($created_at)->toDateTimeString();
    }
}
