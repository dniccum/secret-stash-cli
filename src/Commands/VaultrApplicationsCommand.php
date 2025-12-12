<?php

namespace Dniccum\VaultrCli\Commands;

use Dniccum\VaultrCli\VaultrClient;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class VaultrApplicationsCommand extends Command
{
    protected $signature = 'vaultr:applications
                            {action? : The action to perform (list, show, create)}
                            {--organization= : Organization ID}
                            {--id= : Application ID for show action}
                            {--name= : Application name for create action}';

    protected $description = 'Manage Vaultr applications';

    public function handle(VaultrClient $client): int
    {
        $action = $this->argument('action') ?? 'list';

        try {
            match ($action) {
                'list' => $this->listApplications($client),
                'show' => $this->showApplication($client),
                'create' => $this->createApplication($client),
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

    protected function listApplications(VaultrClient $client): void
    {
        $organizationId = $this->getOrganizationId($client);

        info('Fetching applications...');

        $response = $client->getApplications($organizationId);
        $applications = $response['data'] ?? [];

        if (empty($applications)) {
            info('No applications found.');

            return;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Applications</>');
        $this->newLine();

        $rows = array_map(function ($app) {
            return [
                $app['id'],
                $app['name'],
                $app['status'],
                $app['created_at'],
            ];
        }, $applications);

        table(
            ['ID', 'Name', 'Status', 'Created'],
            $rows
        );
    }

    protected function showApplication(VaultrClient $client): void
    {
        $organizationId = $this->getOrganizationId($client);
        $applicationId = $this->option('id');

        if (! $applicationId) {
            $response = $client->getApplications($organizationId);
            $applications = $response['data'] ?? [];

            if (empty($applications)) {
                error('No applications found.');

                return;
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

        info('Fetching application details...');

        $response = $client->getApplication($organizationId, $applicationId);
        $app = $response['data'] ?? null;

        if (! $app) {
            error('Application not found.');

            return;
        }

        $this->newLine();
        $this->line('┌─────────────────────────────────────────────────────────────┐');
        $this->line('│ <fg=cyan;options=bold>Application Details</>                                      │');
        $this->line('├─────────────────────────────────────────────────────────────┤');
        $this->line('│ <fg=yellow>ID:</> '.$app['id'].'                          │');
        $this->line('│ <fg=yellow>Name:</> '.$app['name'].'                                      │');
        $this->line('│ <fg=yellow>Status:</> '.$app['status'].'                                  │');
        $this->line('└─────────────────────────────────────────────────────────────┘');
        $this->newLine();

        if (! empty($app['environments'])) {
            $this->line('<fg=cyan;options=bold>Environments</>');
            $rows = array_map(function ($env) {
                return [
                    $env['id'],
                    $env['name'],
                    $env['type'],
                    $env['variables_count'] ?? 0,
                ];
            }, $app['environments']);

            table(['ID', 'Name', 'Type', 'Variables'], $rows);
        }
    }

    protected function createApplication(VaultrClient $client): void
    {
        $organizationId = $this->getOrganizationId($client);

        $name = $this->option('name') ?? text(
            label: 'What is the application name?',
            placeholder: 'My Application',
            required: true
        );

        info('Creating application...');

        $response = $client->createApplication($organizationId, $name);
        $app = $response['data'] ?? null;

        if ($app) {
            $this->newLine();
            $this->line('<fg=green;options=bold>✓</> Application created successfully!');
            $this->line('<fg=yellow>ID:</> '.$app['id']);
            $this->line('<fg=yellow>Name:</> '.$app['name']);
            $this->newLine();
        } else {
            error('Failed to create application.');
        }
    }
}
