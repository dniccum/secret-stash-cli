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
                ['slug' => 'ci', 'id' => 'env_456', 'name' => 'CI', 'type' => 'testing'],
            ]]);

        // Should never attempt to create variables
        $mock->shouldNotReceive('createVariable');
        $mock->shouldNotReceive('getEnvironmentEnvelope');
    });

    $this->artisan("secret-stash:variables push --environment=ci --file={$tempEnv}")
        ->expectsOutputToContain('This is a testing environment and may only be manipulated within the SecretStash application.')
        ->assertFailed();

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
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123', 'name' => 'Testing', 'type' => 'standard']]]);

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
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123', 'name' => 'Testing', 'type' => 'standard']]]);

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
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123', 'name' => 'Testing', 'type' => 'standard']]]);

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
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123', 'name' => 'Testing', 'type' => 'standard']]]);

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

it('returns failure when user declines environment creation for missing environment', function () {
    $tempEnv = str_replace('\\', '/', tempnam(sys_get_temp_dir(), '.env'));
    File::put($tempEnv, "APP_NAME=SecretStashApp\nDB_PASSWORD=password123");

    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [
                ['slug' => 'staging', 'id' => 'env_456', 'name' => 'Staging', 'type' => 'standard'],
            ]]);

        // Should never attempt to create variables or fetch envelope
        $mock->shouldNotReceive('createVariable');
        $mock->shouldNotReceive('getEnvironmentEnvelope');
    });

    $this->artisan("secret-stash:variables push --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Push 2 variable(s) to your SecretStash application?', true)
        ->expectsQuestion('This environment does not exist. Would you like to create this environment now?', false)
        ->expectsOutputToContain('Push cancelled.')
        ->assertFailed();

    unlink($tempEnv);
})->group('variables');

it('creates environment and completes push when user accepts creation for missing environment', function () {
    $dek = CryptoHelper::generateKey();
    $tempEnv = str_replace('\\', '/', tempnam(sys_get_temp_dir(), '.env'));
    File::put($tempEnv, "APP_NAME=SecretStashApp\nDB_PASSWORD=password123");

    $this->mock(SecretStashClient::class, function ($mock) use ($dek) {
        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [
                ['slug' => 'staging', 'id' => 'env_456', 'name' => 'Staging', 'type' => 'standard'],
            ]]);

        // The sub-command will call createEnvironment on the client
        $mock->shouldReceive('createEnvironment')
            ->once()
            ->with('app_123', 'Testing', 'testing', 'local')
            ->andReturn(['data' => ['name' => 'Testing', 'slug' => 'testing', 'type' => 'local']]);

        // After environment creation, the sub-command lists environments
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'testing', 123)
            ->andReturn(['data' => ['envelope' => CryptoHelper::createEnvelope($dek, $this->pair->public_key)]]);

        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::type('string'), Mockery::any())
            ->andReturn([]);
    });

    $this->artisan("secret-stash:variables push --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Push 2 variable(s) to your SecretStash application?', true)
        ->expectsQuestion('This environment does not exist. Would you like to create this environment now?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    unlink($tempEnv);
})->group('variables');

it('pushes variables with empty values successfully', function () {
    $dek = CryptoHelper::generateKey();
    $tempEnv = str_replace('\\', '/', tempnam(sys_get_temp_dir(), '.env'));
    File::put($tempEnv, "APP_NAME=SecretStashApp\nEMPTY_VAR=\nDB_PASSWORD=password123");

    $this->mock(SecretStashClient::class, function ($mock) use ($dek) {
        $mock->shouldReceive('getEnvironmentEnvelope')
            ->once()
            ->with('app_123', 'testing', 123)
            ->andReturn(['data' => ['envelope' => CryptoHelper::createEnvelope($dek, $this->pair->public_key)]]);

        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123', 'name' => 'Testing', 'type' => 'standard']]]);

        // Should be called for all three variables including the empty one
        $mock->shouldReceive('createVariable')
            ->times(3)
            ->with('app_123', 'testing', Mockery::type('string'), Mockery::on(function ($payload) {
                return is_array($payload)
                    && isset($payload['ct'])
                    && is_string($payload['ct']);
            }))
            ->andReturn([]);
    });

    $this->artisan("secret-stash:variables push --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Push 3 variable(s) to your SecretStash application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 3')
        ->assertSuccessful();

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
        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123', 'name' => 'Testing', 'type' => 'testing']]]);

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
