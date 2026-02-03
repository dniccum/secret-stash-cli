<?php

use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\SecretStashClient;

it('rewraps the environment envelope with a new user key', function () {
    $password = 'old-password';
    $oldKeyPair = CryptoHelper::generateRSAKeyPair();
    $newKeyPair = CryptoHelper::generateRSAKeyPair();
    $dek = CryptoHelper::generateKey();
    $oldEnvelope = CryptoHelper::createEnvelope($dek, $oldKeyPair['public_key']);

    $payload = CryptoHelper::encryptPrivateKey($oldKeyPair['private_key'], $password);
    $tempFile = tempnam(sys_get_temp_dir(), 'old_key_');
    file_put_contents($tempFile, json_encode($payload));

    $this->mock(SecretStashClient::class, function ($mock) use ($oldEnvelope, $newKeyPair, $dek) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('env_123')
            ->andReturn(['data' => ['envelope' => $oldEnvelope]]);

        $mock->shouldReceive('getUserKeys')
            ->once()
            ->andReturn(['data' => ['public_key' => $newKeyPair['public_key']]]);

        $mock->shouldReceive('storeEnvironmentEnvelope')
            ->once()
            ->with('env_123', Mockery::on(function ($payload) use ($newKeyPair, $dek) {
                $opened = CryptoHelper::openEnvelope($payload, $newKeyPair['private_key']);

                return $opened === $dek;
            }))
            ->andReturn(['data' => []]);
    });

    $this->artisan('secret-stash:envelope rewrap --application=app_123 --environment=env_123 --old-key-file='.$tempFile)
        ->expectsQuestion('Enter the old private key password', $password)
        ->expectsOutputToContain('Envelope rewrapped successfully!')
        ->assertSuccessful();

    unlink($tempFile);
});

it('resets the environment envelope and displays success output', function () {
    $keyPair = CryptoHelper::generateRSAKeyPair();

    $this->mock(SecretStashClient::class, function ($mock) use ($keyPair) {
        $mock->shouldReceive('getUserKeys')
            ->once()
            ->andReturn(['data' => ['public_key' => $keyPair['public_key']]]);

        $mock->shouldReceive('storeEnvironmentEnvelope')
            ->once()
            ->with('env_123', Mockery::on(function ($payload) use ($keyPair) {
                $opened = CryptoHelper::openEnvelope($payload, $keyPair['private_key']);

                return strlen($opened) === 32;
            }))
            ->andReturn(['data' => []]);
    });

    $this->artisan('secret-stash:envelope reset --application=app_123 --environment=env_123')
        ->expectsOutputToContain('Environment key reset successfully!')
        ->expectsOutputToContain('Re-upload your variables to encrypt them with the new key.')
        ->assertSuccessful();
});

it('repairs the environment envelope by falling back to reset', function () {
    $password = 'old-password';
    $oldKeyPair = CryptoHelper::generateRSAKeyPair();
    $newKeyPair = CryptoHelper::generateRSAKeyPair();
    $dek = CryptoHelper::generateKey();
    $oldEnvelope = CryptoHelper::createEnvelope($dek, $oldKeyPair['public_key']);

    $payload = CryptoHelper::encryptPrivateKey($oldKeyPair['private_key'], $password);
    $tempFile = tempnam(sys_get_temp_dir(), 'old_key_');
    file_put_contents($tempFile, json_encode($payload));

    $this->mock(SecretStashClient::class, function ($mock) use ($oldEnvelope, $newKeyPair) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('env_123')
            ->andReturn(['data' => ['envelope' => $oldEnvelope]]);

        $mock->shouldReceive('getUserKeys')
            ->twice()
            ->andReturn(
                ['data' => []],
                ['data' => ['public_key' => $newKeyPair['public_key']]]
            );

        $mock->shouldReceive('storeEnvironmentEnvelope')
            ->once()
            ->with('env_123', Mockery::on(function ($payload) use ($newKeyPair) {
                $opened = CryptoHelper::openEnvelope($payload, $newKeyPair['private_key']);

                return strlen($opened) === 32;
            }))
            ->andReturn(['data' => []]);
    });

    $this->artisan('secret-stash:envelope repair --application=app_123 --environment=env_123 --old-key-file='.$tempFile)
        ->expectsQuestion('Enter the old private key password', $password)
        ->expectsConfirmation('Unable to rewrap the envelope. Reset the environment key and continue?', 'yes')
        ->expectsOutputToContain('Environment key reset successfully!')
        ->assertSuccessful();

    unlink($tempFile);
});
