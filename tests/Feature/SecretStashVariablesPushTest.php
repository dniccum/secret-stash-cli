<?php

use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\SecretStashClient;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Create Meta
    $meta = [
        'device_key_id' => 123,
        'label' => 'Test Device',
        'public_key' => $this->pair->public_key,
        'fingerprint' => CryptoHelper::fingerprint($this->pair->public_key),
    ];
    file_put_contents($this->dir.'/device.json', json_encode($meta));
    $this->meta = $meta;
});

it('blocks push to a testing environment with an error message', function () {
    $tempEnv = str_replace('\\', '/', tempnam(sys_get_temp_dir(), '.env'));
    File::put($tempEnv, "APP_NAME=SecretStashApp\nDB_PASSWORD=password123");

    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [
                ['slug' => 'ci', 'id' => 'env_456', 'type' => 'testing'],
            ]]);

        // Should never attempt to create variables
        $mock->shouldNotReceive('createVariable');
        $mock->shouldNotReceive('getEnvironmentEnvelope');
    });

    $this->artisan("secret-stash:variables push --environment=ci --file={$tempEnv}")
        ->expectsOutputToContain('This is a testing environment and may only be manipulated within the SecretStash application.')
        ->assertSuccessful();

    unlink($tempEnv);
})->group('variables');

it('skips variables with SECRET_STASH_ prefix when pushing', function () {
    // Arrange
    $dek = CryptoHelper::generateKey();
    $tempEnv = str_replace('\\', '/', tempnam(sys_get_temp_dir(), '.env'));
    File::put($tempEnv, "APP_NAME=SecretStashApp\nSECRET_STASH_API_TOKEN=secret_token\nDB_PASSWORD=password123\nSECRET_STASH_URL=https://secret-stash.io");

    $this->mock(SecretStashClient::class, function ($mock) use ($dek) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'testing', 123)
            ->andReturn(['data' => ['envelope' => CryptoHelper::createEnvelope($dek, $this->pair->public_key)]]);

        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123']]]);

        // Should only be called for APP_NAME and DB_PASSWORD
        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::on(function ($name) {
                return ! str_starts_with($name, 'SECRET_STASH_');
            }), Mockery::any())
            ->andReturn([]);
    });

    // Act & Assert
    $this->artisan("secret-stash:variables push --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Push 2 variable(s) to your SecretStash application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    // Cleanup
    unlink($tempEnv);
})->group('variables');

it('skips commented variables when pushing', function () {
    // Arrange
    $dek = CryptoHelper::generateKey();
    $tempEnv = str_replace('\\', '/', tempnam(sys_get_temp_dir(), '.env'));
    $envContent = <<<'EOD'
APP_NAME=SecretStashApp
# COMMENTED_VAR=hidden
# Another comment
DB_PASSWORD=password123
#PUSH_ME=false
EOD;
    File::put($tempEnv, $envContent);

    $this->mock(SecretStashClient::class, function ($mock) use ($dek) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'testing', 123)
            ->andReturn(['data' => ['envelope' => CryptoHelper::createEnvelope($dek, $this->pair->public_key)]]);

        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123']]]);

        // Should only be called for APP_NAME and DB_PASSWORD
        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::on(function ($name) {
                return ! in_array($name, ['COMMENTED_VAR', 'PUSH_ME']);
            }), Mockery::any())
            ->andReturn([]);
    });

    // Act & Assert
    $this->artisan("secret-stash:variables push --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Push 2 variable(s) to your SecretStash application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    // Cleanup
    unlink($tempEnv);
})->group('variables');

it('skips variables with inline comments if they are at the start of the line', function () {
    // Arrange
    $dek = CryptoHelper::generateKey();
    $tempEnv = str_replace('\\', '/', tempnam(sys_get_temp_dir(), '.env'));
    $envContent = <<<'EOD'
  # LEADING_SPACE_COMMENT=value
NOT_COMMENTED=true # INLINE_COMMENT=not_a_var
WITH_HASH="value#with#hash"
EOD;
    File::put($tempEnv, $envContent);

    $this->mock(SecretStashClient::class, function ($mock) use ($dek) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'testing', 123)
            ->andReturn(['data' => ['envelope' => CryptoHelper::createEnvelope($dek, $this->pair->public_key)]]);

        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123']]]);

        // Should only be called for NOT_COMMENTED and WITH_HASH
        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::on(function ($name) {
                return in_array($name, ['NOT_COMMENTED', 'WITH_HASH']);
            }), Mockery::any())
            ->andReturn([]);
    });

    // Act & Assert
    $this->artisan("secret-stash:variables push --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Push 2 variable(s) to your SecretStash application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    // Cleanup
    unlink($tempEnv);
})->group('variables');

it('skips variables defined in ignored_variables config when pushing', function () {
    // Arrange
    $dek = CryptoHelper::generateKey();
    $tempEnv = str_replace('\\', '/', tempnam(sys_get_temp_dir(), '.env'));
    File::put($tempEnv, "APP_NAME=SecretStashApp\nIGNORE_ME=true\nDB_PASSWORD=password123");

    // Mock config
    config(['secret-stash.ignored_variables' => ['IGNORE_ME']]);

    $this->mock(SecretStashClient::class, function ($mock) use ($dek) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'testing', 123)
            ->andReturn(['data' => ['envelope' => CryptoHelper::createEnvelope($dek, $this->pair->public_key)]]);

        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123']]]);

        // Should only be called for APP_NAME and DB_PASSWORD
        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::on(function ($name) {
                return $name !== 'IGNORE_ME';
            }), Mockery::any())
            ->andReturn([]);
    });

    // Act & Assert
    $this->artisan("secret-stash:variables push --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Push 2 variable(s) to your SecretStash application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    // Cleanup
    unlink($tempEnv);
})->group('variables');

it('skips variables defined in ignored_variables config when pulling', function () {
    // Arrange
    $dek = CryptoHelper::generateKey();
    $tempEnv = str_replace('\\', '/', tempnam(sys_get_temp_dir(), '.env'));
    File::put($tempEnv, 'EXISTING_VAR=old_value');

    // Mock config
    config(['secret-stash.ignored_variables' => ['IGNORE_ME_TOO']]);

    $this->mock(SecretStashClient::class, function ($mock) use ($dek) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'testing', 123)
            ->andReturn(['data' => ['envelope' => CryptoHelper::createEnvelope($dek, $this->pair->public_key)]]);

        $mock->makePartial();
        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => '1', 'name' => 'NEW_VAR', 'created_at' => '2025-01-01 00:00:00', 'payload' => CryptoHelper::aesGcmEncrypt('added_value', $dek)],
                    ['id' => '2', 'name' => 'IGNORE_ME_TOO', 'created_at' => '2025-01-01 00:00:00', 'payload' => CryptoHelper::aesGcmEncrypt('should_not_be_here', $dek)],
                ],
            ]);
    });

    // Act & Assert
    $this->artisan("secret-stash:variables pull --environment=testing --file={$tempEnv}")
        ->expectsOutputToContain('Variables pulled successfully!')
        ->assertSuccessful();

    $content = File::get($tempEnv);
    expect($content)->toContain('NEW_VAR=added_value')
        ->not->toContain('IGNORE_ME_TOO');

    // Cleanup
    unlink($tempEnv);
})->group('variables');
