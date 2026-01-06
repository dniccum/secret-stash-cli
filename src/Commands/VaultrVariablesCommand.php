<?php

namespace Dniccum\Vaultr\Commands;

use Dniccum\Vaultr\Crypto\CryptoHelper;
use Dniccum\Vaultr\Exceptions\InvalidEnvironmentConfiguration;
use Dniccum\Vaultr\VaultrClient;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class VaultrVariablesCommand extends BasicCommand
{
    protected $signature = 'vaultr:variables
                            {action? : The action to perform (list, pull, push)}
                            {--application= : Application ID}
                            {--environment= : Environment ID}
                            {--file= : .env file path for pull/push actions}';

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
        } catch (InvalidEnvironmentConfiguration|\Throwable $e) {
            error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function listVariables(VaultrClient $client): void
    {
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

        $rows = array_map(function ($var) {
            return [
                $var['name'],
            ];
        }, $variables);

        table(
            ['Name'],
            $rows
        );

        $this->newLine();
        info('Total: '.count($variables).' variable(s)');
    }

    protected function pullVariables(VaultrClient $client): void
    {
        $filePath = $this->option('file') ?? '.env';

        info('Fetching variables from Vaultr...');

        $applicationId = $this->applicationId;
        $environmentId = $this->environmentSlug;

        $response = $client->getVariables($applicationId, $environmentId);
        $variables = $response['data'] ?? [];

        if (empty($variables)) {
            info('No variables found.');

            return;
        }

        $client->syncEnvFromVariables($variables, $filePath);

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Variables pulled successfully!');
        $this->line('<fg=yellow>File:</> '.$filePath);
        $this->line('<fg=yellow>Variables:</> '.count($variables));
        $this->newLine();
    }

    protected function pushVariables(VaultrClient $client): void
    {
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
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $variables[$parts[0]] = $parts[1];
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
        $key = $this->getEnvironmentKey($this->environmentSlug);

        $environments = $client->getEnvironments($this->applicationId);
        if (count($environments['data']) === 0) {
            $this->createEnvironment();
        } else {
            $slugList = array_map(fn ($env) => $env['slug'], $environments['data']);
            if (!in_array($this->environmentSlug, $slugList, true)) {
                $this->createEnvironment();
            }
        }

        spin(
            callback: function () use ($client, $variables, &$created, &$failed, $key) {
                foreach ($variables as $name => $value) {
                    try {
                        $payload = CryptoHelper::aesGcmEncrypt($value, $key);
                        $client->createVariable($this->applicationId, $this->environmentSlug, $name, $payload);
                        $created++;
                    } catch (\Exception $e) {
                        logger()->debug($e->getMessage(), ['environment' => $this->environmentSlug, 'variable' => $name]);
                        $failed++;
                    }
                }
            },
            message: 'Pushing variables to Vaultr...'
        );

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Push completed!');
        $this->line('<fg=yellow>Created:</> '.$created);
        if ($failed > 0) {
            $this->line('<fg=red>Failed:</> '.$failed.' (may already exist)');
        }
        $this->newLine();
    }

    protected function getEnvironmentKey(string $environmentSlug): string
    {
        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/tmp';
        $keysFile = $homeDir.'/.vaultr/keys.json';

        $encodedKey = null;
        if (file_exists($keysFile)) {
            $keys = json_decode(file_get_contents($keysFile), true);
            $encodedKey = $keys[$environmentSlug] ?? null;
        }

        if (! $encodedKey) {
            // TODO get `APP_ENV` from `.env` file
            $encodedKey = text(
                label: "No key found for environment {$environmentSlug}. Let's generate one now.",
                required: true
            );
            \Artisan::call('vaultr:keys generate', ['--environment' => $environmentSlug]);
        }

        return CryptoHelper::base64urlDecode($encodedKey);
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
            '--slug' => $this->environmentSlug
        ]);
        info('Environment successfully created. Continuing with push...');
    }
}
