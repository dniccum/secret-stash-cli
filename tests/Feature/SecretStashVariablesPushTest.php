<?php

use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\SecretStashClient;
use Illuminate\Support\Facades\File;

it('skips variables with SECRET_STASH_ prefix when pushing', function () {
    // Arrange
    $key = '12345678901234567890123456789012';
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, "APP_NAME=SecretStashApp\nSECRET_STASH_API_TOKEN=secret_token\nDB_PASSWORD=password123\nSECRET_STASH_URL=https://secret-stash.io");

    $this->mock(SecretStashClient::class, function ($mock) {
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
    $this->artisan("secret-stash:variables push --application=app_123 --environment=testing --file={$tempEnv} --key={$key}")
        ->expectsQuestion('Push 2 variable(s) to your SecretStash application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    // Cleanup
    unlink($tempEnv);
});

it('skips commented variables when pushing', function () {
    // Arrange
    $key = '12345678901234567890123456789012';
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    $envContent = <<<'EOD'
APP_NAME=SecretStashApp
# COMMENTED_VAR=hidden
# Another comment
DB_PASSWORD=password123
#PUSH_ME=false
EOD;
    File::put($tempEnv, $envContent);

    $this->mock(SecretStashClient::class, function ($mock) {
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
    $this->artisan("secret-stash:variables push --application=app_123 --environment=testing --file={$tempEnv} --key={$key}")
        ->expectsQuestion('Push 2 variable(s) to your SecretStash application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    // Cleanup
    unlink($tempEnv);
});

it('skips variables with inline comments if they are at the start of the line', function () {
    // Arrange
    $key = '12345678901234567890123456789012';
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    $envContent = <<<'EOD'
  # LEADING_SPACE_COMMENT=value
NOT_COMMENTED=true # INLINE_COMMENT=not_a_var
WITH_HASH="value#with#hash"
EOD;
    File::put($tempEnv, $envContent);

    $this->mock(SecretStashClient::class, function ($mock) {
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
    $this->artisan("secret-stash:variables push --application=app_123 --environment=testing --file={$tempEnv} --key={$key}")
        ->expectsQuestion('Push 2 variable(s) to your SecretStash application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    // Cleanup
    unlink($tempEnv);
});

it('skips variables defined in ignored_variables config when pushing', function () {
    // Arrange
    $key = '12345678901234567890123456789012';
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, "APP_NAME=SecretStashApp\nIGNORE_ME=true\nDB_PASSWORD=password123");

    // Mock config
    config(['secret-stash.ignored_variables' => ['IGNORE_ME']]);

    $this->mock(SecretStashClient::class, function ($mock) {
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
    $this->artisan("secret-stash:variables push --application=app_123 --environment=testing --file={$tempEnv} --key={$key}")
        ->expectsQuestion('Push 2 variable(s) to your SecretStash application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    // Cleanup
    unlink($tempEnv);
});

it('skips variables defined in ignored_variables config when pulling', function () {
    // Arrange
    $key = '12345678901234567890123456789012';
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, 'EXISTING_VAR=old_value');

    // Mock config
    config(['secret-stash.ignored_variables' => ['IGNORE_ME_TOO']]);

    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->makePartial();
        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    ['name' => 'NEW_VAR', 'payload' => ['value' => 'added_value']],
                    ['name' => 'IGNORE_ME_TOO', 'payload' => ['value' => 'should_not_be_here']],
                ],
            ]);
    });

    // Act & Assert
    $this->artisan("secret-stash:variables pull --application=app_123 --environment=testing --file={$tempEnv} --key={$key}")
        ->expectsOutputToContain('Variables pulled successfully!')
        ->assertSuccessful();

    $content = File::get($tempEnv);
    expect($content)->toContain('NEW_VAR=added_value')
        ->not->toContain('IGNORE_ME_TOO');

    // Cleanup
    unlink($tempEnv);
});
