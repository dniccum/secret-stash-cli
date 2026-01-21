<?php

namespace Dniccum\Vaultr\Commands;

use Dniccum\Vaultr\Exceptions\InvalidEnvironmentConfiguration;
use Dniccum\Vaultr\VaultrClient;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class VaultrEnvironmentsCommand extends BasicCommand
{
    protected $signature = 'vaultr:environments
                            {action? : The action to perform (list, create)}
                            {--application= : Application ID}
                            {--name= : Name of the environment to create}
                            {--slug= : The environment slug (used to reference the environment in your application configuration)}
                            {--type= : Environment type for create action (local, development, production)}';

    protected $aliases = [
        'vaultr:env',
    ];

    protected $description = 'Manage Vaultr environments';

    public function handle(VaultrClient $client): int
    {
        $action = $this->argument('action') ?? 'list';

        try {
            $this->setEnvironment();

            match ($action) {
                'list' => $this->listEnvironments($client),
                'create' => $this->createEnvironment($client),
                default => error("Unknown action: {$action}"),
            };

            return self::SUCCESS;
        } catch (\Throwable $e) {
            error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function listEnvironments(VaultrClient $client): void
    {
        info('Fetching environments...');

        $response = $client->getEnvironments($this->applicationId);
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
                $env['slug'],
                $env['type'],
                $env['variables_count'] ?? 0,
                $env['created_at'],
            ];
        }, $environments);

        $this->info("Found ".count($environments)." environment(s)");

        table(
            ['ID', 'Name', 'Slug', 'Type', 'Variables', 'Created'],
            $rows
        );
    }

    protected function createEnvironment(VaultrClient $client): void
    {
        $name = $this->option('name') ?? text(
            label: 'What is the environment name?',
            placeholder: 'Production',
            required: true
        );

        $slug = $this->option('slug') ?? text(
            label: 'What should the environment slug be?',
            default: \Str::slug($name),
            required: true,
            hint: 'This will be used to reference the environment in your application configuration.',
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

        $response = $client->createEnvironment($this->applicationId, $name, $slug, $type);
        $env = $response['data'] ?? null;

        if ($env) {
            $this->newLine();
            $this->line('<fg=green;options=bold>✓</> Environment created successfully!');
            $this->line('<fg=yellow>Name:</> '.$env['name']);
            $this->line('<fg=yellow>Slug:</> '.$env['slug']);
            $this->line('<fg=yellow>Type:</> '.$env['type']);
            $this->newLine();
        } else {
            error('Failed to create environment.');
        }
    }
}
