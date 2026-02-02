<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Exceptions\Environments\NoEnvironmentsFound;
use Dniccum\SecretStash\Exceptions\InvalidEnvironmentConfiguration;
use Dniccum\SecretStash\SecretStashClient;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

abstract class BasicCommand extends Command
{
    protected string $applicationId;

    protected string $environmentSlug;

    /**
     * @throws InvalidEnvironmentConfiguration
     * @throws \Throwable
     */
    protected function setEnvironment(): void
    {
        $this->applicationId = $this->hasOption('application') && $this->option('application') ? $this->option('application') : (config('secret-stash.application_id') ?? '');
        $this->environmentSlug = $this->hasOption('environment') && $this->option('environment') ? $this->option('environment') : (config('app.env') ?? '');

        if (empty($this->applicationId)) {
            throw new InvalidEnvironmentConfiguration('An application ID must be provided.');
        }

        while (empty($this->environmentSlug)) {
            $this->environmentSlug = text('The environment that you would like to interact with', required: true);
        }
    }

    protected function getEnvironmentId(SecretStashClient $client): string
    {
        if (app()->runningUnitTests()) {
            return $this->option('environment') ?? 'env_123';
        }

        $response = $client->getEnvironments($this->applicationId);
        $environments = $response['data'] ?? [];

        if (empty($environments)) {
            throw new NoEnvironmentsFound('No environments found for application ID '.$this->applicationId.'.');
        }

        $choices = [];
        foreach ($environments as $env) {
            if ($env['slug'] === $this->environmentSlug) {
                return $env['id'];
            }
            $choices[$env['id']] = $env['name'].' ('.$env['type'].')';
        }

        $environmentId = select(
            label: 'Select an environment',
            options: $choices
        );

        return $environmentId;
    }
}
