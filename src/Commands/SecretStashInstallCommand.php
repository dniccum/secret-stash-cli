<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Support\ConfigResolver;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class SecretStashInstallCommand extends BasicCommand
{
    /**
     * @var string
     */
    protected $signature = 'secret-stash:install
                            {--force : Skip confirmation prompts}';

    /**
     * @var string
     */
    protected $description = 'Install the SecretStash CLI';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Config publishing is only available in Laravel
        if (ConfigResolver::isLaravel()) {
            if ($this->option('force') || confirm(
                label: 'Would you like to publish the SecretStash config file?',
            )) {
                $this->call('vendor:publish', [
                    '--tag' => 'secret-stash-config',
                ]);
            }
        }

        if (! $this->ensureApiToken()) {
            return self::FAILURE;
        }

        $this->call('secret-stash:keys', [
            'action' => 'init',
            '--force' => (bool) $this->option('force'),
        ]);

        info('SecretStash has been successfully initialized!');

        return self::SUCCESS;
    }

    /**
     * Registering device keys requires an API token, so offer to log in when one is missing.
     */
    protected function ensureApiToken(): bool
    {
        if (! empty(ConfigResolver::get('api_token'))) {
            return true;
        }

        warning('No SecretStash API token is configured.');

        if ($this->option('force') || ! confirm(label: 'Would you like to log in to SecretStash now to generate one?')) {
            $this->missingApiTokenError();

            return false;
        }

        $this->call('secret-stash:login');

        ConfigResolver::clearCache();

        if (empty(ConfigResolver::get('api_token'))) {
            $this->missingApiTokenError();

            return false;
        }

        return true;
    }

    protected function missingApiTokenError(): void
    {
        error('An API token is required to register this device. Run "secret-stash:login" or set SECRET_STASH_API_TOKEN in your .env file, then run "secret-stash:install" again.');
    }
}
