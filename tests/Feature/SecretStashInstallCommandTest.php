<?php

use Dniccum\SecretStash\SecretStashClient;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $dir = sys_get_temp_dir().'/.secret-stash';
    @unlink($dir.'/device_private_key.pem');
    @unlink($dir.'/device.json');

    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->shouldReceive('storeDeviceKey')
            ->andReturn(['data' => ['id' => 1, 'label' => 'test', 'public_key' => 'pk', 'fingerprint' => 'fp']]);
    });
});

it('can run the secret-stash:install command', function () {
    Config::set('secret-stash.application_id', 'app-123');
    Config::set('app.env', 'testing');

    $this->artisan('secret-stash:install')
        ->expectsConfirmation('Would you like to publish the SecretStash config file?', 'yes')
        ->expectsOutputToContain('SecretStash has been successfully initialized!')
        ->assertSuccessful();
});

it('can run the secret-stash:install command and skip config publishing', function () {
    Config::set('secret-stash.application_id', 'app-123');
    Config::set('app.env', 'testing');

    $this->artisan('secret-stash:install')
        ->expectsConfirmation('Would you like to publish the SecretStash config file?', 'no')
        ->expectsOutputToContain('SecretStash has been successfully initialized!')
        ->assertSuccessful();
});

it('does not require an application ID or environment to install', function () {
    Config::set('secret-stash.application_id', null);
    Config::set('app.env', null);

    $this->artisan('secret-stash:install')
        ->expectsConfirmation('Would you like to publish the SecretStash config file?', 'no')
        ->expectsOutputToContain('SecretStash has been successfully initialized!')
        ->assertSuccessful();
});

it('skips the config publishing confirmation with --force', function () {
    Config::set('secret-stash.application_id', 'app-123');
    Config::set('app.env', 'testing');

    $this->artisan('secret-stash:install', ['--force' => true])
        ->expectsOutputToContain('SecretStash has been successfully initialized!')
        ->assertSuccessful();
});

it('fails cleanly when no API token is configured and login is declined', function () {
    Config::set('secret-stash.api_token', null);

    $this->artisan('secret-stash:install')
        ->expectsConfirmation('Would you like to publish the SecretStash config file?', 'no')
        ->expectsConfirmation('Would you like to log in to SecretStash now to generate one?', 'no')
        ->expectsOutputToContain('An API token is required to register this device.')
        ->doesntExpectOutputToContain('SecretStash has been successfully initialized!')
        ->assertFailed();
});

it('does not start the login flow with --force when no API token is configured', function () {
    Config::set('secret-stash.api_token', null);

    $this->artisan('secret-stash:install', ['--force' => true])
        ->expectsOutputToContain('An API token is required to register this device.')
        ->assertFailed();
});

it('runs the login command and continues the install when no API token is configured', function () {
    Config::set('secret-stash.api_token', null);

    $login = new class extends Command
    {
        protected $signature = 'secret-stash:login';

        public function handle(): int
        {
            Config::set('secret-stash.api_token', 'new-token');

            return self::SUCCESS;
        }
    };

    $this->app->make(Kernel::class)->registerCommand($login);

    $this->artisan('secret-stash:install')
        ->expectsConfirmation('Would you like to publish the SecretStash config file?', 'no')
        ->expectsConfirmation('Would you like to log in to SecretStash now to generate one?', 'yes')
        ->expectsOutputToContain('SecretStash has been successfully initialized!')
        ->assertSuccessful();
});

it('fails cleanly when login does not produce an API token', function () {
    Config::set('secret-stash.api_token', null);

    $login = new class extends Command
    {
        protected $signature = 'secret-stash:login';

        public function handle(): int
        {
            return self::FAILURE;
        }
    };

    $this->app->make(Kernel::class)->registerCommand($login);

    $this->artisan('secret-stash:install')
        ->expectsConfirmation('Would you like to publish the SecretStash config file?', 'no')
        ->expectsConfirmation('Would you like to log in to SecretStash now to generate one?', 'yes')
        ->expectsOutputToContain('An API token is required to register this device.')
        ->assertFailed();
});
