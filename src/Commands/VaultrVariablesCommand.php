<?php

namespace Dniccum\VaultrCli\Commands;

use Dniccum\VaultrCli\VaultrClient;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class VaultrVariablesCommand extends Command
{
    protected $signature = 'vaultr:variables
                            {action? : The action to perform (list, create, update, delete, pull, push)}
                            {--organization= : Organization ID}
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
            match ($action) {
                'list' => $this->listVariables($client),
                'create' => $this->createVariable($client),
                'update' => $this->updateVariable($client),
                'delete' => $this->deleteVariable($client),
                'pull' => $this->pullVariables($client),
                'push' => $this->pushVariables($client),
                default => error("Unknown action: {$action}"),
            };

            return self::SUCCESS;
        } catch (\Exception $e) {
            error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function getOrganizationId(VaultrClient $client): string
    {
        $organizationId = $this->option('organization') ?? config('vaultr.default_organization_id');

        if (! $organizationId) {
            $response = $client->getOrganizations();
            $organizations = $response['data'] ?? [];

            if (empty($organizations)) {
                throw new \RuntimeException('No organizations found.');
            }

            $choices = [];
            foreach ($organizations as $org) {
                $choices[$org['id']] = $org['name'];
            }

            $organizationId = select(
                label: 'Select an organization',
                options: $choices
            );
        }

        return $organizationId;
    }

    protected function getApplicationId(VaultrClient $client, string $organizationId): string
    {
        $applicationId = $this->option('application');

        if (! $applicationId) {
            $response = $client->getApplications($organizationId);
            $applications = $response['data'] ?? [];

            if (empty($applications)) {
                throw new \RuntimeException('No applications found.');
            }

            $choices = [];
            foreach ($applications as $app) {
                $choices[$app['id']] = $app['name'];
            }

            $applicationId = select(
                label: 'Select an application',
                options: $choices
            );
        }

        return $applicationId;
    }

    protected function getEnvironmentId(VaultrClient $client, string $organizationId, string $applicationId): string
    {
        $environmentId = $this->option('environment');

        if (! $environmentId) {
            $response = $client->getEnvironments($organizationId, $applicationId);
            $environments = $response['data'] ?? [];

            if (empty($environments)) {
                throw new \RuntimeException('No environments found.');
            }

            $choices = [];
            foreach ($environments as $env) {
                $choices[$env['id']] = $env['name'].' ('.$env['type'].')';
            }

            $environmentId = select(
                label: 'Select an environment',
                options: $choices
            );
        }

        return $environmentId;
    }

    protected function listVariables(VaultrClient $client): void
    {
        $organizationId = $this->getOrganizationId($client);
        $applicationId = $this->getApplicationId($client, $organizationId);
        $environmentId = $this->getEnvironmentId($client, $organizationId, $applicationId);

        info('Fetching variables...');

        $response = $client->getVariables($organizationId, $applicationId, $environmentId);
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
                $var['id'],
                $var['name'],
                str_repeat('•', min(strlen($var['value']), 20)),
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

    protected function createVariable(VaultrClient $client): void
    {
        $organizationId = $this->getOrganizationId($client);
        $applicationId = $this->getApplicationId($client, $organizationId);
        $environmentId = $this->getEnvironmentId($client, $organizationId, $applicationId);

        $name = $this->option('name') ?? text(
            label: 'Variable name',
            placeholder: 'DATABASE_URL',
            required: true
        );

        $value = $this->option('value') ?? password(
            label: 'Variable value',
            placeholder: 'Enter the value',
            required: true
        );

        info('Creating variable...');

        $response = $client->createVariable($organizationId, $applicationId, $environmentId, $name, $value);
        $var = $response['data'] ?? null;

        if ($var) {
            $this->newLine();
            $this->line('<fg=green;options=bold>✓</> Variable created successfully!');
            $this->line('<fg=yellow>Name:</> '.$var['name']);
            $this->newLine();
        } else {
            error('Failed to create variable.');
        }
    }

    protected function updateVariable(VaultrClient $client): void
    {
        $organizationId = $this->getOrganizationId($client);
        $applicationId = $this->getApplicationId($client, $organizationId);
        $environmentId = $this->getEnvironmentId($client, $organizationId, $applicationId);

        $response = $client->getVariables($organizationId, $applicationId, $environmentId);
        $variables = $response['data'] ?? [];

        if (empty($variables)) {
            error('No variables found.');

            return;
        }

        $choices = [];
        foreach ($variables as $var) {
            $choices[$var['id']] = $var['name'];
        }

        $variableId = select(
            label: 'Select a variable to update',
            options: $choices
        );

        $name = text(
            label: 'New variable name',
            placeholder: 'DATABASE_URL',
            required: true
        );

        $value = password(
            label: 'New variable value (leave empty to keep current)',
            placeholder: 'Enter the value',
            required: false
        );

        info('Updating variable...');

        $response = $client->updateVariable($organizationId, $applicationId, $environmentId, $variableId, $name, $value ?: null);

        if ($response) {
            $this->newLine();
            $this->line('<fg=green;options=bold>✓</> Variable updated successfully!');
            $this->newLine();
        } else {
            error('Failed to update variable.');
        }
    }

    protected function deleteVariable(VaultrClient $client): void
    {
        $organizationId = $this->getOrganizationId($client);
        $applicationId = $this->getApplicationId($client, $organizationId);
        $environmentId = $this->getEnvironmentId($client, $organizationId, $applicationId);

        $response = $client->getVariables($organizationId, $applicationId, $environmentId);
        $variables = $response['data'] ?? [];

        if (empty($variables)) {
            error('No variables found.');

            return;
        }

        $choices = [];
        foreach ($variables as $var) {
            $choices[$var['id']] = $var['name'];
        }

        $variableId = select(
            label: 'Select a variable to delete',
            options: $choices
        );

        $confirmed = confirm(
            label: 'Are you sure you want to delete this variable?',
            default: false
        );

        if (! $confirmed) {
            info('Deletion cancelled.');

            return;
        }

        info('Deleting variable...');

        $client->deleteVariable($organizationId, $applicationId, $environmentId, $variableId);

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Variable deleted successfully!');
        $this->newLine();
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
