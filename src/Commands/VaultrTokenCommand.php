<?php

namespace Dniccum\VaultrCli\Commands;

use Dniccum\VaultrCli\VaultrClient;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class VaultrTokenCommand extends Command
{
    protected $signature = 'vaultr:token
                            {action? : The action to perform (list, create)}
                            {--name= : Token name for creation}';

    protected $description = 'Manage Vaultr API tokens';

    public function handle(VaultrClient $client): int
    {
        $action = $this->argument('action') ?? 'list';

        try {
            match ($action) {
                'list' => $this->listTokens($client),
                'create' => $this->createToken($client),
                default => $this->error("Unknown action: {$action}"),
            };

            return self::SUCCESS;
        } catch (\Exception $e) {
            error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function listTokens(VaultrClient $client): void
    {
        info('Fetching your API tokens...');

        $response = $client->get('tokens');
        $tokens = $response['data'] ?? [];

        if (empty($tokens)) {
            info('No API tokens found.');

            return;
        }

        $rows = array_map(function ($token) {
            return [
                $token['id'],
                $token['name'],
                $token['last_used_at'] ?? 'Never',
                $token['created_at'],
            ];
        }, $tokens);

        table(
            ['ID', 'Name', 'Last Used', 'Created'],
            $rows
        );
    }

    protected function createToken(VaultrClient $client): void
    {
        $name = $this->option('name') ?? text(
            label: 'What should this token be called?',
            placeholder: 'My API Token',
            required: true
        );

        info('Creating API token...');

        $response = $client->post('tokens', ['name' => $name]);
        $token = $response['data']['token'] ?? null;

        if ($token) {
            $this->newLine();
            $this->line('┌─────────────────────────────────────────────────────────────┐');
            $this->line('│ <fg=green;options=bold>API Token Created Successfully!</>                         │');
            $this->line('├─────────────────────────────────────────────────────────────┤');
            $this->line('│ <fg=yellow>Please save this token securely. You won\'t be able to</>  │');
            $this->line('│ <fg=yellow>see it again!</>                                          │');
            $this->line('├─────────────────────────────────────────────────────────────┤');
            $this->line('│ Token:                                                      │');
            $this->line('│ <fg=cyan>'.$token.'</>  │');
            $this->line('└─────────────────────────────────────────────────────────────┘');
            $this->newLine();
            info('Add this to your .env file as: VAULTR_API_TOKEN='.$token);
        } else {
            error('Failed to create token.');
        }
    }
}
