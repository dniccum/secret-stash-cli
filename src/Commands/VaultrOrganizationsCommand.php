<?php

namespace Dniccum\VaultrCli\Commands;

use Dniccum\VaultrCli\VaultrClient;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class VaultrOrganizationsCommand extends Command
{
    protected $signature = 'vaultr:organizations
                            {action? : The action to perform (list, show, create)}
                            {--id= : Organization ID for show action}
                            {--name= : Organization name for create action}';

    protected $description = 'Manage Vaultr organizations';

    public function handle(VaultrClient $client): int
    {
        $action = $this->argument('action') ?? 'list';

        try {
            match ($action) {
                'list' => $this->listOrganizations($client),
                'show' => $this->showOrganization($client),
                'create' => $this->createOrganization($client),
                default => error("Unknown action: {$action}"),
            };

            return self::SUCCESS;
        } catch (\Exception $e) {
            error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function listOrganizations(VaultrClient $client): void
    {
        info('Fetching your organizations...');

        $response = $client->getOrganizations();
        $organizations = $response['data'] ?? [];

        if (empty($organizations)) {
            info('No organizations found.');

            return;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Your Organizations</>');
        $this->newLine();

        $rows = array_map(function ($org) {
            return [
                $org['id'],
                $org['name'],
                $org['users_count'] ?? 0,
                $org['is_owner'] ? '✓' : '✗',
            ];
        }, $organizations);

        table(
            ['ID', 'Name', 'Members', 'Owner'],
            $rows
        );
    }

    protected function showOrganization(VaultrClient $client): void
    {
        $organizationId = $this->option('id');

        if (! $organizationId) {
            $response = $client->getOrganizations();
            $organizations = $response['data'] ?? [];

            if (empty($organizations)) {
                error('No organizations found.');

                return;
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

        info('Fetching organization details...');

        $response = $client->getOrganization($organizationId);
        $org = $response['data'] ?? null;

        if (! $org) {
            error('Organization not found.');

            return;
        }

        $this->newLine();
        $this->line('┌─────────────────────────────────────────────────────────────┐');
        $this->line('│ <fg=cyan;options=bold>Organization Details</>                                     │');
        $this->line('├─────────────────────────────────────────────────────────────┤');
        $this->line('│ <fg=yellow>ID:</> '.$org['id'].'                          │');
        $this->line('│ <fg=yellow>Name:</> '.$org['name'].'                                      │');
        $this->line('│ <fg=yellow>Owner:</> '.$org['owner']['name'].' ('.$org['owner']['email'].')   │');
        $this->line('└─────────────────────────────────────────────────────────────┘');
        $this->newLine();

        if (! empty($org['members'])) {
            $this->line('<fg=cyan;options=bold>Members</>');
            $rows = array_map(function ($member) {
                return [
                    $member['name'],
                    $member['email'],
                ];
            }, $org['members']);

            table(['Name', 'Email'], $rows);
        }
    }

    protected function createOrganization(VaultrClient $client): void
    {
        $name = $this->option('name') ?? text(
            label: 'What is the organization name?',
            placeholder: 'My Organization',
            required: true
        );

        info('Creating organization...');

        $response = $client->createOrganization($name);
        $org = $response['data'] ?? null;

        if ($org) {
            $this->newLine();
            $this->line('<fg=green;options=bold>✓</> Organization created successfully!');
            $this->line('<fg=yellow>ID:</> '.$org['id']);
            $this->line('<fg=yellow>Name:</> '.$org['name']);
            $this->newLine();
        } else {
            error('Failed to create organization.');
        }
    }
}
