<?php

use Dniccum\SecretStash\SecretStashClient;

it('rejects TTL below minimum of 5 minutes', function () {
    $this->artisan('secret-stash:keys init --temporary --ttl=3')
        ->expectsOutputToContain('TTL must be between 5 and 60 minutes.')
        ->assertFailed();
});

it('rejects TTL above maximum of 60 minutes', function () {
    $this->artisan('secret-stash:keys init --temporary --ttl=61')
        ->expectsOutputToContain('TTL must be between 5 and 60 minutes.')
        ->assertFailed();
});

it('creates a temporary key with default TTL', function () {
    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->shouldReceive('storeDeviceKey')
            ->once()
            ->withArgs(function ($label, $publicKey, $keyType, $metadata, $isTemporary, $ttlMinutes) {
                return $isTemporary === true
                    && $ttlMinutes === 15
                    && $keyType === 'device'
                    && $metadata['temporary'] === true
                    && $metadata['ttl_minutes'] === 15;
            })
            ->andReturn([
                'data' => [
                    'id' => 99,
                    'label' => 'CI/CD Temporary Key',
                    'public_key' => 'test-public-key',
                    'fingerprint' => 'test-fingerprint',
                    'expires_at' => '2026-03-25T23:15:00+00:00',
                ],
            ]);
    });

    $this->artisan('secret-stash:keys init --temporary')
        ->expectsOutputToContain('Initializing Temporary Device Key')
        ->expectsOutputToContain('Expires in 15 minutes')
        ->expectsOutputToContain('Temporary device key registered!')
        ->expectsOutputToContain('SECRET_STASH_KEY_DIR=')
        ->assertSuccessful();
});

it('creates a temporary key with custom TTL', function () {
    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->shouldReceive('storeDeviceKey')
            ->once()
            ->withArgs(function ($label, $publicKey, $keyType, $metadata, $isTemporary, $ttlMinutes) {
                return $isTemporary === true && $ttlMinutes === 30;
            })
            ->andReturn([
                'data' => [
                    'id' => 100,
                    'label' => 'CI/CD Temporary Key',
                    'public_key' => 'test-public-key',
                    'fingerprint' => 'test-fingerprint',
                    'expires_at' => '2026-03-25T23:30:00+00:00',
                ],
            ]);
    });

    $this->artisan('secret-stash:keys init --temporary --ttl=30')
        ->expectsOutputToContain('Expires in 30 minutes')
        ->assertSuccessful();
});

it('uses custom label when provided for temporary key', function () {
    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->shouldReceive('storeDeviceKey')
            ->once()
            ->withArgs(function ($label) {
                return $label === 'My CI Key';
            })
            ->andReturn([
                'data' => [
                    'id' => 101,
                    'label' => 'My CI Key',
                    'public_key' => 'test-public-key',
                    'fingerprint' => 'test-fingerprint',
                    'expires_at' => '2026-03-25T23:15:00+00:00',
                ],
            ]);
    });

    $this->artisan('secret-stash:keys init --temporary --label="My CI Key"')
        ->expectsOutputToContain('Temporary device key registered!')
        ->assertSuccessful();
});
