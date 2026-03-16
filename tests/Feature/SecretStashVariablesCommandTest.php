<?php

use Dniccum\SecretStash\Commands\SecretStashVariablesCommand;
use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\SecretStashClient;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->meta = [
        'device_key_id' => 123,
        'label' => 'Test Device',
        'public_key' => $this->pair->public_key,
        'fingerprint' => CryptoHelper::fingerprint($this->pair->public_key),
    ];
    file_put_contents($this->dir.'/device.json', json_encode($this->meta));
});

it('can run the variables:list command and display results', function () {
    $dek = CryptoHelper::generateKey();

    $this->mock(SecretStashClient::class, function ($mock) use ($dek) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'testing', $this->meta['device_key_id'])
            ->andReturn(['data' => ['envelope' => CryptoHelper::createEnvelope($dek, $this->meta['public_key'])]]);

        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => 'var_1', 'name' => 'APP_NAME', 'created_at' => '2025-01-01 00:00:00', 'payload' => CryptoHelper::aesGcmEncrypt('SecretStash', $dek)],
                    ['id' => 'var_2', 'name' => 'APP_ENV', 'created_at' => '2025-01-02 00:00:00', 'payload' => CryptoHelper::aesGcmEncrypt('testing', $dek)],
                ],
            ]);
    });

    $this->artisan('secret-stash:variables list --environment=testing')
        ->expectsOutputToContain('Environment Variables')
        ->expectsOutputToContain('Total: 2 variable(s)')
        ->assertSuccessful();
})->group('variables');

it('lists variables when at least one exists', function () {
    $dek = CryptoHelper::generateKey();

    $this->mock(SecretStashClient::class, function ($mock) use ($dek) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'testing', 123)
            ->andReturn(['data' => ['envelope' => CryptoHelper::createEnvelope($dek, $this->meta['public_key'])]]);

        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => 'var_1', 'name' => 'FOO', 'created_at' => '2025-01-01 00:00:00', 'payload' => CryptoHelper::aesGcmEncrypt('bar', $dek)],
                ],
            ]);
    });

    $this->artisan('secret-stash:variables list --environment=testing')
        ->expectsOutputToContain('Environment Variables')
        ->expectsOutputToContain('Total: 1 variable(s)')
        ->assertSuccessful();
})->group('variables');

it('updates .env when variables are pulled with various key formats', function () {
    $dek = CryptoHelper::generateKey();

    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, "existing_var=old_value\nMIXED_Case=stay_same");

    $this->mock(SecretStashClient::class, function ($mock) use ($dek) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'testing', 123)
            ->andReturn(['data' => ['envelope' => CryptoHelper::createEnvelope($dek, $this->meta['public_key'])]]);

        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => '1', 'name' => 'EXISTING_VAR', 'created_at' => '2025-01-01 00:00:00', 'payload' => CryptoHelper::aesGcmEncrypt('new_value', $dek)],
                    ['id' => '2', 'name' => 'new_var', 'created_at' => '2025-01-01 00:00:00', 'payload' => CryptoHelper::aesGcmEncrypt('added_value', $dek)],
                    ['id' => '3', 'name' => 'EMPTY_VAR', 'created_at' => '2025-01-01 00:00:00', 'payload' => CryptoHelper::aesGcmEncrypt('', $dek)],
                    ['id' => '4', 'name' => 'SPACE_VAR', 'created_at' => '2025-01-01 00:00:00', 'payload' => CryptoHelper::aesGcmEncrypt('has space', $dek)],
                ],
            ]);
    });

    $this->artisan("secret-stash:variables pull --environment=testing --file={$tempEnv}")
        ->expectsOutputToContain('Variables pulled successfully!')
        ->assertSuccessful();

    $content = File::get($tempEnv);
    expect($content)->toContain('EXISTING_VAR=new_value')
        ->toContain('new_var=added_value')
        ->toContain('EMPTY_VAR=')
        ->toContain('SPACE_VAR=has space');

    unlink($tempEnv);
})->group('variables');

it('pulls and writes decrypted values into .env', function () {
    $dek = CryptoHelper::generateKey();

    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, '');

    $this->mock(SecretStashClient::class, function ($mock) use ($dek) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'testing', 123)
            ->andReturn(['data' => ['envelope' => CryptoHelper::createEnvelope($dek, $this->meta['public_key'])]]);

        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    [
                        'id' => '019bd30d-ca8a-7241-9499-b9e7e8d4fbf4',
                        'name' => 'MAIL_FROM_ADDRESS',
                        'created_at' => '2025-01-01 00:00:00',
                        'payload' => CryptoHelper::aesGcmEncrypt('hello@example.com', $dek),
                    ],
                ],
            ]);
    });

    $this->artisan("secret-stash:variables pull --environment=testing --file={$tempEnv}")
        ->expectsOutputToContain('Fetching variables from SecretStash...')
        ->expectsOutputToContain('Variables pulled successfully!')
        ->assertSuccessful();

    $content = File::get($tempEnv);
    expect($content)->toContain('MAIL_FROM_ADDRESS=hello@example.com');

    unlink($tempEnv);
})->group('variables');

it('correctly reads APP_ENV from .env file', function () {
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, "APP_ENV=staging\n");

    $command = Mockery::mock(SecretStashVariablesCommand::class)->makePartial();
    $command->shouldAllowMockingProtectedMethods();
    $command->shouldReceive('option')->with('file')->andReturn($tempEnv);

    expect($command->getAppEnvFromEnvFile())->toBe('staging');

    unlink($tempEnv);
})->group('variables');

it('correctly reads APP_ENV from .env file with quotes', function () {
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, "APP_ENV=\"production\"\n");

    $command = Mockery::mock(SecretStashVariablesCommand::class)->makePartial();
    $command->shouldAllowMockingProtectedMethods();
    $command->shouldReceive('option')->with('file')->andReturn($tempEnv);

    expect($command->getAppEnvFromEnvFile())->toBe('production');

    unlink($tempEnv);
})->group('variables');
