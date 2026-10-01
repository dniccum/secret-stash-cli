<?php

use Dniccum\SecretStash\SecretStashClient;
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
