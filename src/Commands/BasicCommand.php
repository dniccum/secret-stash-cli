<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Contracts\ApplicationEnvironmentVariable;
use Dniccum\SecretStash\Exceptions\Environments\NoEnvironmentsFound;
use Dniccum\SecretStash\Exceptions\InvalidEnvironmentConfiguration;
use Dniccum\SecretStash\SecretStashClient;
use Dniccum\SecretStash\Support\ConfigResolver;
use Illuminate\Console\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
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

        $customDir = ConfigResolver::get('key_dir');
        $this->path = (! ConfigResolver::isRunningTests() && $customDir) ? $customDir : $this->defaultPrivateKeyDirectory();
        $this->privateKeyFile = $this->path.'/device_private_key.pem';
        $this->deviceMetaFile = $this->path.'/device.json';

        if (! is_dir($this->path)) {
            mkdir($this->path, 0700, true);
        }
    }

    protected function defaultPrivateKeyDirectory(): string
    {
        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/tmp';
        if (ConfigResolver::isRunningTests()) {
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
        $this->applicationId = $this->getApplicationOption() ? $this->option('application') : (ConfigResolver::get('application_id') ?? '');
        $this->environmentSlug = $this->getEnvironmentOption() ? $this->option('environment') : (ConfigResolver::get('app_env') ?? '');

        if (empty($this->applicationId)) {
            throw new InvalidEnvironmentConfiguration('An application ID must be provided.');
        }

        while (empty($this->environmentSlug)) {
            $this->environmentSlug = text('The environment that you would like to interact with', required: true);
        }
    }

    private function getApplicationOption(): ?string
    {
        try {
            return $this->option('application');
        } catch (InvalidArgumentException $e) {
            return null;
        }
    }

    private function getEnvironmentOption(): ?string
    {
        try {
            return $this->option('environment');
        } catch (InvalidArgumentException $e) {
            return null;
        }
    }

    protected function invalidAction(?string $action): int
    {
        error("Invalid action: {$action}");

        return self::FAILURE;
    }

    /**
     * @return array<ApplicationEnvironmentVariable>
     */
    protected function getVariablesForEnvironment(SecretStashClient $client): array
    {
        $response = $client->getVariables($this->applicationId, $this->environmentSlug);
        $variables = $response['data'] ?? [];

        if (empty($variables)) {
            error('No variables found.');

            return [];
        }

        return array_map(fn ($var) => new ApplicationEnvironmentVariable(
            id: $var['id'],
            name: $var['name'],
            payload: $var['payload'],
            created_at: $var['created_at'],
        ), $variables);
    }

    /**
     * Check if the current environment slug exists in the given environment list.
     *
     * @param  array  $envData  The list of environments from the API
     * @return bool True if the environment exists, false otherwise
     */
    protected function environmentExists(array $envData): bool
    {
        if (empty($envData)) {
            return false;
        }

        $slugList = array_map(fn ($env) => $env['slug'], $envData);

        return in_array($this->environmentSlug, $slugList, true);
    }

    /**
     * Fetch environments for the current application and validate the target environment exists.
     * Returns the environment data array.
     *
     * @throws NoEnvironmentsFound
     */
    protected function fetchAndValidateEnvironments(SecretStashClient $client): array
    {
        $response = $client->getEnvironments($this->applicationId);
        $envData = $response['data'] ?? [];

        if (empty($envData)) {
            throw new NoEnvironmentsFound('No environments found for application ID '.$this->applicationId.'.');
        }

        if (! $this->environmentExists($envData)) {
            $slugList = array_map(fn ($env) => $env['name'].' ('.$env['slug'].')', $envData);

            throw new NoEnvironmentsFound('The "'.$this->environmentSlug.'" environment does not exist for this application. Available environments: '.implode(', ', $slugList));
        }

        return $envData;
    }

    /**
     * @return array<int, string>
     */
    protected function ignoredVariables(): array
    {
        return ConfigResolver::ignoredVariables();
    }

    /**
     * Create a new SecretStashClient instance.
     * Used by commands to resolve the client without Laravel's DI container.
     */
    protected function resolveClient(): SecretStashClient
    {
        return new SecretStashClient;
    }

    /**
     * Override execute to support standalone mode without Laravel's service container.
     * When running outside Laravel, handle() is called directly without DI.
     *
     * @phpstan-ignore method.notFound
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // If running inside Laravel, use the parent (DI-enabled) execute
        if (ConfigResolver::isLaravel()) {
            return parent::execute($input, $output);
        }

        // Standalone mode: call handle() directly without dependency injection
        return (int) $this->handle();
    }
}
