<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Commands\Traits\UsesApplicationId;
use Dniccum\SecretStash\Contracts\ApplicationEnvironmentVariable;
use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\SecretStashClient;
use Dniccum\SecretStash\Support\VariableUtility;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class SecretStashVariablesCommand extends BasicCommand
{
    use UsesApplicationId;

    protected VariableUtility $variableUtility;

    protected $signature = 'secret-stash:variables
                            {action? : The action to perform (list, pull, push)}
                            {--application= : The unique application ID that identifies your application within SecretStash}
                            {--environment= : Environment slug (defaults to APP_ENV value in .env file if set, otherwise prompts user to select an environment)}
                            {--file= : .env file path for pull/push actions}';

    protected $aliases = [
        'secret-stash:var',
    ];

    protected $description = 'Manage SecretStash environment variables';

    public function __construct()
    {
        parent::__construct();

        $this->variableUtility = new VariableUtility($this->ignoredVariables());
    }

    public function handle(SecretStashClient $client): int
    {
        $action = $this->argument('action') ?? 'list';

        try {
            $this->setEnvironment();

            return match ($action) {
                'list' => $this->listVariables($client),
                'pull' => $this->pullVariables($client),
                'push' => $this->pushVariables($client),
                default => $this->invalidAction($action),
            };
        } catch (\Throwable $e) {
            error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolvePrivateKey(SecretStashKeysCommand $keysCommand): string
    {
        return $keysCommand->getPrivateKey();
    }

    protected function makeKeysCommand(): SecretStashKeysCommand
    {
        return new SecretStashKeysCommand;
    }

    /**
     * @throws \Exception
     */
    protected function listVariables(SecretStashClient $client): int
    {
        $this->fetchAndValidateEnvironments($client);

        $environmentId = $this->environmentSlug;
        $key = $this->getEnvironmentKey($environmentId, $client);

        info('Fetching variables from SecretStash...');

        $variables = $this->getVariablesForEnvironment($client);

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Environment Variables</>');
        $this->newLine();

        $rows = array_map(function (ApplicationEnvironmentVariable $var) use ($key) {
            $decryptedValue = '[Error decrypting]';
            try {
                if ($var->payload === null) {
                    $decryptedValue = '[No value]';
                } else {
                    $decryptedValue = CryptoHelper::aesGcmDecrypt($var->payload, $key);
                }
            } catch (\Throwable $e) {
                // Keep the error message
            }

            return [
                $var->id,
                $var->name,
                str_repeat('•', min(strlen($decryptedValue), 20)),
                $var->created_at,
            ];
        }, $variables);

        table(
            ['ID', 'Name', 'Value', 'Created'],
            $rows
        );

        $this->newLine();
        info('Total: '.count($variables).' variable(s)');

        return self::SUCCESS;
    }

    /**
     * @throws \Exception
     */
    protected function pullVariables(SecretStashClient $client): int
    {
        $this->fetchAndValidateEnvironments($client);

        $environmentId = $this->environmentSlug;
        $key = $this->getEnvironmentKey($environmentId, $client);

        $filePath = $this->option('file') ?? '.env';

        info('Fetching variables from SecretStash...');

        $variables = $this->getVariablesForEnvironment($client);

        $decryptedVariables = [];
        $ignored = $this->ignoredVariables();
        foreach ($variables as $var) {
            try {
                $name = $var->name;
                if (VariableUtility::isIgnoredVariable($name, $ignored)) {
                    continue;
                }

                $payload = $var->payload;
                if ($payload === null) {
                    $decryptedVariables[$name] = '';

                    continue;
                }
                $decryptedValue = CryptoHelper::aesGcmDecrypt($payload, $key);
                $decryptedVariables[$name] = $decryptedValue;
            } catch (\Throwable $e) {
                $name = $var->name;
                error("Failed to decrypt variable: {$name}");
            }
        }

        $existingContent = file_exists($filePath) ? file_get_contents($filePath) : '';
        $mergedContent = VariableUtility::mergeEnvContent($existingContent ?: '', $decryptedVariables);
        file_put_contents($filePath, $mergedContent);

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Variables pulled successfully!');
        $this->line('<fg=yellow>File:</> '.$filePath);
        $this->line('<fg=yellow>Variables:</> '.count($decryptedVariables));
        $this->newLine();

        return self::SUCCESS;
    }

    protected function pushVariables(SecretStashClient $client): int
    {
        $environments = $client->getEnvironments($this->applicationId);
        $envData = $environments['data'] ?? [];

        // Check if the target environment is a testing environment
        foreach ($envData as $env) {
            if ($env['slug'] === $this->environmentSlug && ($env['type'] ?? '') === 'testing') {
                error('This is a testing environment and may only be manipulated within the SecretStash application.');

                return self::FAILURE;
            }
        }

        $filePath = $this->option('file') ?? '.env';

        if (! file_exists($filePath)) {
            error("File not found: {$filePath}");

            return self::FAILURE;
        }

        info('Reading .env file...');

        $content = file_get_contents($filePath);
        $variables = VariableUtility::parseEnvContent($content ?: '');
        $variables = $this->variableUtility->filter($variables);

        if (empty($variables)) {
            error('No variables found in file.');

            return self::FAILURE;
        }

        $confirmed = confirm(
            label: 'Push '.count($variables).' variable(s) to your SecretStash application?',
            default: true
        );

        if (! $confirmed) {
            info('Push cancelled.');

            return self::SUCCESS;
        }

        // Ensure the target environment exists before attempting to get the key
        if (! $this->environmentExists($envData)) {
            if (! $this->createEnvironment()) {
                return self::FAILURE;
            }
        }

        $environmentId = $this->environmentSlug;
        $key = $this->getEnvironmentKey($environmentId, $client);

        $created = 0;
        $failed = 0;

        spin(
            callback: function () use ($client, $variables, &$created, &$failed, $key) {
                foreach ($variables as $name => $value) {
                    $payload = null;
                    try {
                        if ($value !== '') {
                            $payload = CryptoHelper::aesGcmEncrypt($value, $key);
                        } else {
                            $payload = CryptoHelper::aesGcmEncrypt('null', $key);
                        }

                        throw_if($payload === null, \Exception::class, "Payload for '$name' cannot be null.");
                        $client->createVariable($this->applicationId, $this->environmentSlug, $name, $payload);
                        $created++;
                    } catch (\Exception $e) {
                        logger()->debug($e->getMessage(), [
                            'environment' => $this->environmentSlug,
                            'variable' => $name,
                            'value' => $value,
                        ]);
                        $failed++;
                    }
                }
            },
            message: 'Pushing variables to SecretStash...'
        );

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Push completed!');
        $this->line('<fg=yellow>Created or Updated:</> '.$created);
        if ($failed > 0) {
            $this->line('<fg=red>Failed:</> '.$failed.' (may already exist)');
        }
        $this->newLine();

        return self::SUCCESS;
    }

    protected function getAppEnvFromEnvFile(): ?string
    {
        $filePath = $this->option('file') ?? '.env';

        if (! file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2 && $parts[0] === 'APP_ENV') {
                return trim($parts[1], '"\'');
            }
        }

        return null;
    }

    protected function createEnvironment(): bool
    {
        $confirmCreate = confirm(
            label: 'This environment does not exist. Would you like to create this environment now?',
            default: true
        );
        if (! $confirmCreate) {
            info('Push cancelled.');

            return false;
        }
        $exitCode = $this->call('secret-stash:environments', [
            'action' => 'create',
            '--name' => str($this->environmentSlug)->title()->toString(),
            '--slug' => $this->environmentSlug,
        ]);

        if ($exitCode !== self::SUCCESS) {
            error('Failed to create the environment. Push cancelled.');

            return false;
        }

        info('Environment successfully created. Continuing with push...');

        return true;
    }

    protected function getEnvironmentKey(string $environmentId, SecretStashClient $client): string
    {
        $keysCommand = $this->makeKeysCommand();
        $deviceKeyId = $keysCommand->getDeviceKeyId();

        $response = $client->getEnvironmentEnvelope($this->applicationId, $environmentId, $deviceKeyId);
        $envelope = $response['data']['envelope'] ?? null;

        if ($envelope) {
            $privateKey = $this->resolvePrivateKey($keysCommand);

            try {
                return CryptoHelper::openEnvelope($envelope, $privateKey);
            } catch (\Throwable $e) {
                throw new \RuntimeException('Unable to decrypt environment key. Verify your device key or run "secret-stash:envelope repair" if needed.');
            }
        }

        // No envelope exists - first time setup for this environment
        info('No envelope found. Creating new environment encryption key...');

        // Generate new DEK
        $dek = CryptoHelper::generateKey();
        // Get user's device keys and create envelopes
        // Get user's public key and create envelope
        try {
            $userKeysResponse = $client->getUserKeys();
            $deviceKeys = $userKeysResponse['data'] ?? [];

            if (empty($deviceKeys)) {
                throw new \RuntimeException('No device keys found. Run "secret-stash:keys init" first.');
            }

            $envelopes = [];
            foreach ($deviceKeys as $deviceKey) {
                $envelopes[] = [
                    'device_key_id' => $deviceKey['id'],
                    'envelope' => CryptoHelper::createEnvelope($dek, $deviceKey['public_key']),
                ];
            }

            $client->storeBulkEnvironmentEnvelopes($this->applicationId, $environmentId, $envelopes);

            info('Environment key created and secured for your devices.');

            return $dek;
        } catch (\Exception $e) {
            error('Failed to create envelope: '.$e->getMessage());
            throw $e;
        }
    }
}
