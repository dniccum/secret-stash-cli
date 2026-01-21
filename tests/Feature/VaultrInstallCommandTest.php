<?php

use Illuminate\Support\Facades\Config;

it('can run the vaultr:install command', function () {
    Config::set('vaultr.application_id', 'app-123');
    Config::set('app.env', 'testing');

    $this->artisan('vaultr:install')
        ->expectsConfirmation('Would you like to publish the Vaultr config file?', 'yes')
        ->expectsOutputToContain('Vaultr has been successfully initialized!')
        ->assertSuccessful();
});

it('can run the vaultr:install command and skip config publishing', function () {
    Config::set('vaultr.application_id', 'app-123');
    Config::set('app.env', 'testing');

    $this->artisan('vaultr:install')
        ->expectsConfirmation('Would you like to publish the Vaultr config file?', 'no')
        ->expectsOutputToContain('Vaultr has been successfully initialized!')
        ->assertSuccessful();
});

it('prompts for environment if not set', function () {
    Config::set('vaultr.application_id', 'app-123');
    Config::set('app.env', null);

    $this->artisan('vaultr:install')
        ->expectsConfirmation('Would you like to publish the Vaultr config file?', 'no')
        ->expectsQuestion('The environment that you would like to interact with', 'staging')
        ->expectsOutputToContain('Vaultr has been successfully initialized!')
        ->assertSuccessful();
});
