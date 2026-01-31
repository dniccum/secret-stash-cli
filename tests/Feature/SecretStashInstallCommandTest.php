<?php

use Illuminate\Support\Facades\Config;

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

it('prompts for environment if not set', function () {
    Config::set('secret-stash.application_id', 'app-123');
    Config::set('app.env', null);

    $this->artisan('secret-stash:install')
        ->expectsConfirmation('Would you like to publish the SecretStash config file?', 'no')
        ->expectsQuestion('The environment that you would like to interact with', 'staging')
        ->expectsOutputToContain('SecretStash has been successfully initialized!')
        ->assertSuccessful();
});
