<?php

namespace Dniccum\Vaultr\Commands;

use Dniccum\Vaultr\Crypto\CryptoHelper;
use Dniccum\Vaultr\Exceptions\Environments\NoEnvironmentsFound;
use Dniccum\Vaultr\Exceptions\Keys\PrivateKeyNotFound;
use Dniccum\Vaultr\VaultrClient;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class VaultrVariablesCommand extends BasicCommand
{
    protected $signature = 'vaultr:variables
                            {action? : The action to perform (list, pull, push)}
                            {--application= : Application ID}
                            {--environment= : Environment slug (defaults to APP_ENV value in .env file if set, otherwise prompts user to select an environment)}
                            {--file= : .env file path for pull/push actions}
                            {--key= : Encryption key for pull/push actions}';

    protected $description = 'Manage Vaultr environment variables';

    public function handle(VaultrClient $client): int
    {
        $action = $this->argument('action') ?? 'list';

        try {
            $this->setEnvironment();

            match ($action) {
                'list' => $this->listVariables($client),
                'pull' => $this->pullVariables($client),
                'push' => $this->pushVariables($client),
                default => error("Unknown action: {$action}"),
            };

            return self::SUCCESS;
        } catch (\Throwable $e) {
            error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @throws \Exception
     */
    protected function listVariables(VaultrClient $client): void
    {
        $environmentId = $this->getEnvironmentId($client, $this->applicationId);
        $key = $this->getEnvironmentKey($environmentId, $client);
        info('Fetching variables...');

        $response = $client->getVariables($this->applicationId, $this->environmentSlug);
        $variables = $response['data'] ?? [];

        if (empty($variables)) {
            info('No variables found.');

            return;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Environment Variables</>');
        $this->newLine();

        $rows = array_map(function ($var) use ($key) {
            $decryptedValue = '[Error decrypting]';
            try {
                $decryptedValue = CryptoHelper::aesGcmDecrypt($var['payload'], $key);
            } catch (\Exception $e) {
                // Keep the error message
            }

            return [
                $var['id'],
                $var['name'],
                str_repeat('•', min(strlen($decryptedValue), 20)),
                $var['created_at'],
            ];
        }, $variables);

        table(
            ['ID', 'Name', 'Value', 'Created'],
            $rows
        );

        $this->newLine();
        info('Total: '.count($variables).' variable(s)');
    }

    protected function pullVariables(VaultrClient $client): void
    {
        $environmentId = $this->getEnvironmentId($client, $this->applicationId);
        $key = $this->getEnvironmentKey($environmentId, $client);
        $filePath = $this->option('file') ?? '.env';

        info('Fetching variables from Vaultr...');

        $applicationId = $this->applicationId;
        $environmentId = $this->environmentSlug;

        $rawKey = null;
        if ($key) {
            if (strlen($key) === 32) {
                $rawKey = $key;
            } else {
                try {
                    $rawKey = CryptoHelper::base64urlDecode($key);
                } catch (\Throwable $e) {
                    // Fallback or ignore
                }
            }
        }

        $response = $client->getVariables($applicationId, $environmentId);
        $variables = $response['data'] ?? [];

        if (empty($variables)) {
            info('No variables found.');

            return;
        }

        $client->syncEnvFromVariables($variables, $filePath, $rawKey ? CryptoHelper::base64urlEncode($rawKey) : null);

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Variables pulled successfully!');
        $this->line('<fg=yellow>File:</> '.$filePath);
        $this->line('<fg=yellow>Variables:</> '.count($variables));
        $this->newLine();
    }

    protected function pushVariables(VaultrClient $client): void
    {
        $environmentId = $this->getEnvironmentId($client, $this->applicationId);
        $key = $this->getEnvironmentKey($environmentId, $client);

        $filePath = $this->option('file') ?? '.env';

        if (! file_exists($filePath)) {
            error("File not found: {$filePath}");

            return;
        }

        info('Reading .env file...');

        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        $variables = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine) || str_starts_with($trimmedLine, '#')) {
                continue;
            }

            $parts = explode('=', $trimmedLine, 2);
            if (count($parts) === 2) {
                $variableName = trim($parts[0]);
                $value = trim($parts[1]);

                if (str_starts_with($variableName, 'VAULTR_') || in_array($variableName, config('vaultr.ignored_variables', []), true)) {
                    continue;
                }

                $variables[$variableName] = $value;
            }
        }

        if (empty($variables)) {
            error('No variables found in file.');

            return;
        }

        $confirmed = confirm(
            label: 'Push '.count($variables).' variable(s) to your Vaultr application?',
            default: true
        );

        if (! $confirmed) {
            info('Push cancelled.');

            return;
        }

        $created = 0;
        $failed = 0;

        $environments = $client->getEnvironments($this->applicationId);
        if (count($environments['data']) === 0) {
            $this->createEnvironment();
        } else {
            $slugList = array_map(fn ($env) => $env['slug'], $environments['data']);
            if (! in_array($this->environmentSlug, $slugList, true)) {
                $this->createEnvironment();
            }
        }

        spin(
            callback: function () use ($client, $variables, &$created, &$failed, $key) {
                foreach ($variables as $name => $value) {
                    $payload = null;
                    try {
                        if ($value !== '') {
                            $payload = CryptoHelper::aesGcmEncrypt($value, $key);
                        }
                        if (empty($value)) {
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
            message: 'Pushing variables to Vaultr...'
        );

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Push completed!');
        $this->line('<fg=yellow>Created or Updated:</> '.$created);
        if ($failed > 0) {
            $this->line('<fg=red>Failed:</> '.$failed.' (may already exist)');
        }
        $this->newLine();
    }

    protected function getEnvironmentId(VaultrClient $client, string $applicationId): string
    {
        $response = $client->getEnvironments($applicationId);
        $environments = $response['data'] ?? [];

        if (empty($environments)) {
            throw new NoEnvironmentsFound('No environments found for application ID '.$applicationId.'.');
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

    protected function getEnvironmentKey(string $environmentId, VaultrClient $client): string
    {
        // Try to get envelope from server
        try {
            $response = $client->getEnvironmentEnvelope($environmentId);
            $envelope = $response['data']['envelope'] ?? null;

            if ($envelope) {
                // Decrypt envelope to get DEK
                $keysCommand = new VaultrKeysCommand;
                $userPassword = password(
                    label: 'Enter your private key password',
                    required: true
                );

                $privateKey = $keysCommand->getDecryptedPrivateKey($userPassword);

                return CryptoHelper::openEnvelope($envelope, $privateKey);
            }
        } catch (\Exception $e) {
            // Envelope not found - need to create it
        }

        // No envelope exists - first time setup for this environment
        info('No envelope found. Creating new environment encryption key...');

        // Generate new DEK
        $dek = CryptoHelper::generateKey();

        // Get user's keys to create envelope
        $keysCommand = new VaultrKeysCommand;
        $userPassword = password(
            label: 'Enter your private key password',
            required: true
        );

        // Get user's public key and create envelope
        try {
            $userKeysResponse = $client->getUserKeys();
            $publicKey = $userKeysResponse['data']['public_key'] ?? null;

            if (! $publicKey) {
                throw new PrivateKeyNotFound('No user keys found. Run "vaultr:keys init" first.');
            }

            // Create and upload envelope
            $envelope = CryptoHelper::createEnvelope($dek, $publicKey);
            $client->storeEnvironmentEnvelope($environmentId, $envelope);

            info('Environment key created and secured with your encryption key.');

            return $dek;
        } catch (\Exception $e) {
            error('Failed to create envelope: '.$e->getMessage());
            throw $e;
        }
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

    protected function createEnvironment(): void
    {
        $confirmCreate = confirm(
            label: 'This environment does not exist. Would you like to create this environment now?',
            default: true
        );
        if (! $confirmCreate) {
            info('Push cancelled.');

            return;
        }
        $this->call('vaultr:environments', [
            'action' => 'create',
            '--name' => str($this->environmentSlug)->title()->toString(),
            '--slug' => $this->environmentSlug,
        ]);
        info('Environment successfully created. Continuing with push...');
    }
}
