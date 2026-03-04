<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Contracts\ApplicationEnvironmentVariable;
use Dniccum\SecretStash\Exceptions\Environments\NoEnvironmentsFound;
use Dniccum\SecretStash\Exceptions\InvalidEnvironmentConfiguration;
use Dniccum\SecretStash\SecretStashClient;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

abstract class BasicCommand extends Command
{
    protected string $applicationId;

    protected string $environmentSlug;

    protected readonly string $path;

    protected readonly string $privateKeyFile;

    protected readonly string $deviceMetaFile;

    protected string $keyLocation = '/.secret-stash';

    public function __construct()
    {
        parent::__construct();

        $this->path = $this->defaultPrivateKeyDirectory();
        $this->privateKeyFile = $this->path.'/device_private_key.pem';
        $this->deviceMetaFile = $this->path.'/device.json';

        if (! is_dir($this->path)) {
            mkdir($this->path, 0700, true);
        }
    }

    protected function defaultPrivateKeyDirectory(): string
    {
        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/tmp';
        if (app()->runningUnitTests()) {
            $homeDir = sys_get_temp_dir();
        }

        return $homeDir.$this->keyLocation;
    }

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

        return select(
            label: 'Select an environment',
            options: $choices
        );
    }

    protected function invalidAction(?string $action): int
    {
        error("Invalid action: {$action}");

        return self::FAILURE;
    }

    /**
     * @param SecretStashClient $client
     * @return array<ApplicationEnvironmentVariable>|void
     */
    protected function getVariablesForEnvironment(SecretStashClient $client)
    {
        $response = $client->getVariables($this->applicationId, $this->environmentSlug);
        $variables = $response['data'] ?? [];

        if (empty($variables)) {
            error('No variables found.');

            return;
        }
        return array_map(fn ($var) => new ApplicationEnvironmentVariable(
            id: $var['id'],
            name: $var['name'],
            payload: $var['payload'],
            created_at: $var['created_at'],
        ), $variables);
    }
}
