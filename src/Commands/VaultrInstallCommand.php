<?php

namespace Dniccum\Vaultr\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class VaultrInstallCommand extends BasicCommand
{
    /**
     * @var string
     */
    protected $signature = 'vaultr:install';

    /**
     * @var string
     */
    protected $description = 'Install the Vaultr CLI';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (confirm(
            label: 'Would you like to publish the Vaultr config file?',
        )) {
            $this->call('vendor:publish', [
                '--tag' => 'vaultr-config',
            ]);
        }

        $this->setEnvironment();

        $this->call('vaultr:keys', [
            'action' => 'init',
        ]);

        info('Vaultr has been successfully initialized!');

        return 0;
    }
}
