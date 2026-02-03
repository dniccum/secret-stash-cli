<?php

use Dniccum\SecretStash\SecretStashClient;

it('cancels initialization when keys exist locally', function () {
    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->shouldReceive('getUserKeys')
            ->andReturn(['data' => [
                'public_key' => 'public_key',
                'private_key_payload' => 'private_key_payload',
            ]]);
    });

    $filePath = sys_get_temp_dir().'/.secret-stash/user_key.json';
    $content = '{"v": 1, "alg": "AES-256-GCM", "kdf": "PBKDF2", "iter": 600000, "salt": "OT_LrjLK8d7wVxC_Ay3B2g", "iv": "2DvSfmaZLtcNQrq3", "tag": "hkpt5XwtADC9SBBl5SNQww", "ct": "4jYaWEdgQlYbB02lLM"}';

    // Create the file and write content
    file_put_contents($filePath, $content);

    expect($filePath)->tobeFile();

    // Act & Assert
    $this->artisan('secret-stash:keys init')
        ->expectsConfirmation('Keys already exist locally. Generate new keys? (This will require re-sharing all environments)')
        ->assertSuccessful();

    unlink($filePath);
});

it('cancels initialization when server keys exist and user declines overwrite', function () {
    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->shouldReceive('getUserKeys')
            ->andReturn(['data' => [
                'public_key' => 'public_key',
                'private_key_payload' => 'private_key_payload',
            ]]);
    });

    // Act & Assert
    $this->artisan('secret-stash:keys init')
        ->expectsConfirmation('Keys already exist on the server. Replacing them will require re-sharing all environments. Continue?')
        ->expectsConfirmation('Would you like to sync your existing keys from the server?')
        ->assertSuccessful();
});
