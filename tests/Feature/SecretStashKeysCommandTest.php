<?php

use Dniccum\SecretStash\SecretStashClient;

it('cancels initialization when keys exist locally', function () {
    $this->mock(SecretStashClient::class, function ($mock) {
        // No server interaction needed for this prompt path
    });

    $dir = sys_get_temp_dir().'/.secret-stash';
    if (! is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    // Seed local private key to trigger overwrite prompt
    file_put_contents($dir.'/device_private_key.pem', 'test-private-key');

    // Act & Assert
    $this->artisan('secret-stash:keys init')
        ->expectsConfirmation('Device keys already exist locally. Generate new keys? (This device will need access re-granted)')
        ->assertSuccessful();

    @unlink($dir.'/device_private_key.pem');
});

it('shows guidance to register when no local device record exists', function () {
    // Ensure no local device files remain from previous tests
    $dir = sys_get_temp_dir().'/.secret-stash';
    @unlink($dir.'/device_private_key.pem');
    @unlink($dir.'/device.json');

    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->shouldReceive('getUserKeys')
            ->andReturn(['data' => []]);
    });

    $this->artisan('secret-stash:keys status')
        ->expectsOutputToContain('Run "secret-stash:keys init" to generate and register this device.')
        ->assertSuccessful();
});
