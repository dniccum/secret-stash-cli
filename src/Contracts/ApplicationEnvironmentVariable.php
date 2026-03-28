<?php

namespace Dniccum\SecretStash\Contracts;

class ApplicationEnvironmentVariable
{
    public function __construct(
        public string $id,
        public string $name,
        public ?array $payload = null,
        public ?string $created_at = null
    ) {
        if ($created_at !== null) {
            try {
                $this->created_at = (new \DateTimeImmutable($created_at))->format('Y-m-d H:i:s');
            } catch (\Exception) {
                $this->created_at = $created_at;
            }
        }
    }
}
