<?php

namespace Dniccum\Vaultr\Commands;

use Dniccum\Vaultr\Exceptions\InvalidEnvironmentConfiguration;
use Dniccum\Vaultr\VaultrClient;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class VaultrVariablesCommand extends BasicCommand
{
    protected $signature = 'vaultr:variables
                            {action? : The action to perform (list, create, update, delete, pull, push)}
                            {--application= : Application ID}
                            {--environment= : Environment ID}
                            {--name= : Variable name}
                            {--value= : Variable value}
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
        $organizationId = $this->getOrganizationId($client);
        $applicationId = $this->getApplicationId($client, $organizationId);
        $environmentId = $this->getEnvironmentId($client, $organizationId, $applicationId);

        $filePath = $this->option('file') ?? '.env';

        info('Fetching variables from Vaultr...');

        $response = $client->getVariables($organizationId, $applicationId, $environmentId);
        $variables = $response['data'] ?? [];

        if (empty($variables)) {
            info('No variables found.');

            return;
        }

        $content = '';
        foreach ($variables as $var) {
            $content .= $var['name'].'='.$var['value']."\n";
        }

        file_put_contents($filePath, $content);

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Variables pulled successfully!');
        $this->line('<fg=yellow>File:</> '.$filePath);
        $this->line('<fg=yellow>Variables:</> '.count($variables));
        $this->newLine();
    }

    protected function pushVariables(VaultrClient $client): void
    {
        $organizationId = $this->getOrganizationId($client);
        $applicationId = $this->getApplicationId($client, $organizationId);
        $environmentId = $this->getEnvironmentId($client, $organizationId, $applicationId);

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
            label: 'Push '.count($variables).' variable(s) to Vaultr?',
            default: true
        );

        if (! $confirmed) {
            info('Push cancelled.');

            return;
        }

        $created = 0;
        $failed = 0;

        spin(
            callback: function () use ($client, $organizationId, $applicationId, $environmentId, $variables, &$created, &$failed) {
                foreach ($variables as $name => $value) {
                    try {
                        $client->createVariable($organizationId, $applicationId, $environmentId, $name, $value);
                        $created++;
                    } catch (\Exception $e) {
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
}
