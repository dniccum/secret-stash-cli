<?php

namespace Dniccum\SecretStash\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class SecretStashInstallCommand extends BasicCommand
{
    /**
     * @var string
     */
    protected $signature = 'secret-stash:install';

    /**
     * @var string
     */
    protected $description = 'Install the SecretStash CLI';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (confirm(
            label: 'Would you like to publish the SecretStash config file?',
        )) {
            $this->call('vendor:publish', [
                '--tag' => 'secret-stash-config',
            ]);
        }

        $this->setEnvironment();

        $this->call('secret-stash:keys', [
            'action' => 'init',
        ]);

        info('SecretStash has been successfully initialized!');

        return 0;
    }
}
