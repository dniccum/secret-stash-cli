<?php

use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\SecretStashClient;

it('rewraps the environment envelope with a new user key', function () {
    $oldKeyPair = CryptoHelper::generateRSAKeyPair();
    $dek = CryptoHelper::generateKey();
    $oldEnvelope = CryptoHelper::createEnvelope($dek, $oldKeyPair->public_key);

    // Write old private key PEM to temp file
    $tempFile = tempnam(sys_get_temp_dir(), 'old_key_');
    file_put_contents($tempFile, $oldKeyPair->private_key);

    $this->mock(SecretStashClient::class, function ($mock) use ($oldEnvelope, $dek) {
        // Use old-device-key-id=999 for fetching old envelope
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'env_123', 999)
            ->andReturn(['data' => ['envelope' => $oldEnvelope]]);

        $mock->shouldReceive('storeEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'env_123', 123, Mockery::on(function ($payload) use ($dek) {
                $opened = CryptoHelper::openEnvelope($payload, $this->pair->private_key);

                return $opened === $dek;
            }))
            ->andReturn(['data' => []]);
    });

    $this->artisan('secret-stash:envelope rewrap --environment=env_123 --old-key-file='.$tempFile.' --old-device-key-id=999')
        ->expectsOutputToContain('Envelope rewrapped successfully!')
        ->assertSuccessful();

    unlink($tempFile);
});

it('resets the environment envelope and displays success output', function () {
    $this->mock(SecretStashClient::class, function ($mock) {
        // Reset now creates envelopes for all device keys returned
        $mock->shouldReceive('getUserKeys')
            ->once()
            ->andReturn(['data' => [
                ['id' => 123, 'public_key' => $this->pair->public_key],
            ]]);

        $mock->shouldReceive('storeBulkEnvironmentEnvelopes')
            ->once()
            ->with('app_123', 'env_123', Mockery::on(function ($envelopes) {
                if (! is_array($envelopes) || count($envelopes) !== 1) {
                    return false;
                }
                $env = $envelopes[0]['envelope'] ?? null;
                if (! $env) {
                    return false;
                }
                $opened = CryptoHelper::openEnvelope($env, $this->pair->private_key);

                return strlen($opened) === 32;
            }))
            ->andReturn(['data' => []]);
    });

    $this->artisan('secret-stash:envelope reset --environment=env_123')
        ->expectsOutputToContain('Environment key reset successfully!')
        ->expectsOutputToContain('Re-upload your variables to encrypt them with the new key.')
        ->assertSuccessful();
});

it('repairs the environment envelope by falling back to reset', function () {
    $oldKeyPair = CryptoHelper::generateRSAKeyPair();
    $dek = CryptoHelper::generateKey();

    // Create an envelope that cannot be opened by the provided old private key to force fallback
    $mismatchPair = CryptoHelper::generateRSAKeyPair();
    $badEnvelope = CryptoHelper::createEnvelope($dek, $mismatchPair->public_key);

    // Write old private key PEM to temp file
    $tempFile = tempnam(sys_get_temp_dir(), 'old_key_');
    file_put_contents($tempFile, $oldKeyPair->private_key);

    $this->mock(SecretStashClient::class, function ($mock) use ($badEnvelope) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'env_123', 111)
            ->andReturn(['data' => ['envelope' => $badEnvelope]]);

        // After fallback, reset creates envelopes for all device keys
        $mock->shouldReceive('getUserKeys')
            ->once()
            ->andReturn(['data' => [
                ['id' => 123, 'public_key' => $this->pair->public_key],
            ]]);

        $mock->shouldReceive('storeBulkEnvironmentEnvelopes')
            ->once()
            ->with('app_123', 'env_123', Mockery::on(function ($envelopes) {
                $env = $envelopes[0]['envelope'] ?? null;
                if (! $env) {
                    return false;
                }
                $opened = CryptoHelper::openEnvelope($env, $this->pair->private_key);

                return strlen($opened) === 32;
            }))
            ->andReturn(['data' => []]);
    });

    $this->artisan('secret-stash:envelope repair --environment=env_123 --old-key-file='.$tempFile.' --old-device-key-id=111')
        ->expectsConfirmation('Unable to rewrap the envelope. Reset the environment key and continue?', 'yes')
        ->expectsOutputToContain('Environment key reset successfully!')
        ->assertSuccessful();

    unlink($tempFile);
});
