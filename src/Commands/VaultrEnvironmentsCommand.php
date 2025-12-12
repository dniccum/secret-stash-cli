<?php

namespace Dniccum\VaultrCli\Commands;

use Dniccum\VaultrCli\VaultrClient;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class VaultrEnvironmentsCommand extends Command
{
    protected $signature = 'vaultr:environments
                            {action? : The action to perform (list, create)}
                            {--organization= : Organization ID}
                            {--application= : Application ID}
                            {--name= : Environment name for create action}
                            {--type= : Environment type for create action (local, development, production)}';

    protected $description = 'Manage Vaultr environments';

    public function handle(VaultrClient $client): int
    {
        $action = $this->argument('action') ?? 'list';

        try {
            match ($action) {
                'list' => $this->listEnvironments($client),
                'create' => $this->createEnvironment($client),
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

    protected function listEnvironments(VaultrClient $client): void
    {
        $organizationId = $this->getOrganizationId($client);
        $applicationId = $this->getApplicationId($client, $organizationId);

        info('Fetching environments...');

        $response = $client->getEnvironments($organizationId, $applicationId);
        $environments = $response['data'] ?? [];

        if (empty($environments)) {
            info('No environments found.');

            return;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Environments</>');
        $this->newLine();

        $rows = array_map(function ($env) {
            return [
                $env['id'],
                $env['name'],
                $env['type'],
                $env['variables_count'] ?? 0,
                $env['created_at'],
            ];
        }, $environments);

        table(
            ['ID', 'Name', 'Type', 'Variables', 'Created'],
            $rows
        );
    }

    protected function createEnvironment(VaultrClient $client): void
    {
        $organizationId = $this->getOrganizationId($client);
        $applicationId = $this->getApplicationId($client, $organizationId);

        $name = $this->option('name') ?? text(
            label: 'What is the environment name?',
            placeholder: 'Production',
            required: true
        );

        $type = $this->option('type') ?? select(
            label: 'What type of environment is this?',
            options: [
                'local' => 'Local',
                'development' => 'Development',
                'production' => 'Production',
            ]
        );

        info('Creating environment...');

        $response = $client->createEnvironment($organizationId, $applicationId, $name, $type);
        $env = $response['data'] ?? null;

        if ($env) {
            $this->newLine();
            $this->line('<fg=green;options=bold>✓</> Environment created successfully!');
            $this->line('<fg=yellow>ID:</> '.$env['id']);
            $this->line('<fg=yellow>Name:</> '.$env['name']);
            $this->line('<fg=yellow>Type:</> '.$env['type']);
            $this->newLine();
        } else {
            error('Failed to create environment.');
        }
    }
}
