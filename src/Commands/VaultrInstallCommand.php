<?php

namespace Dniccum\Vaultr\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

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
     *
     * @return int
     */
    public function handle(): int
    {
        if (confirm(
            label: 'Would you like to publish the Vaultr config file?',
            default: true
        )) {
            $this->call('vendor:publish', [
                '--tag' => 'vaultr-config',
            ]);
        }

        $this->setEnvironment();

        $this->callSilently('vaultr:keys', [
            'action' => 'generate',
            '--environment' => $this->environmentSlug,
        ]);

        info('Vaultr has been successfully initialized!');

        return 0;
    }
}
