<?php

use Dniccum\Vaultr\Crypto\CryptoHelper;
use Dniccum\Vaultr\VaultrClient;
use Illuminate\Support\Facades\File;

it('skips variables with VAULTR_ prefix when pushing', function () {
    // Arrange
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, "APP_NAME=VaultrApp\nVAULTR_API_TOKEN=secret_token\nDB_PASSWORD=password123\nVAULTR_URL=https://vaultr.io");

    $this->mock(VaultrClient::class, function ($mock) {
        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [['slug' => 'testing']]]);

        // Should only be called for APP_NAME and DB_PASSWORD
        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::on(function ($name) {
                return ! str_starts_with($name, 'VAULTR_');
            }), Mockery::any())
            ->andReturn([]);
    });

    // Mock CryptoHelper to return a fake payload
    // Note: VaultrVariablesCommand uses CryptoHelper::aesGcmEncrypt
    // We don't necessarily need to mock the static method if it works,
    // but we need to ensure the key is available.

    // We'll mock the home directory for keys.json if needed,
    // or just rely on the fact that we can mock the behavior if it's called.
    // However, the command calls static methods.

    // Create a dummy key file
    $homeDir = sys_get_temp_dir().'/vaultr_test_home';
    if (! file_exists($homeDir)) {
        mkdir($homeDir, 0777, true);
    }
    $keysFile = $homeDir.'/.vaultr/keys.json';
    if (! file_exists(dirname($keysFile))) {
        mkdir(dirname($keysFile), 0777, true);
    }

    $fakeKey = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'; // 32 bytes
    File::put($keysFile, json_encode(['testing' => CryptoHelper::base64urlEncode($fakeKey)]));

    // Set HOME env var for the command to find the keys
    $oldHome = $_SERVER['HOME'] ?? null;
    $_SERVER['HOME'] = $homeDir;

    // Act & Assert
    $this->artisan("vaultr:variables push --application=app_123 --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Push 2 variable(s) to your Vaultr application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    // Cleanup
    unlink($tempEnv);
    File::deleteDirectory($homeDir);
    if ($oldHome) {
        $_SERVER['HOME'] = $oldHome;
    } else {
        unset($_SERVER['HOME']);
    }
});

it('skips commented variables when pushing', function () {
    // Arrange
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    $envContent = <<<'EOD'
APP_NAME=VaultrApp
# COMMENTED_VAR=hidden
# Another comment
DB_PASSWORD=password123
#PUSH_ME=false
EOD;
    File::put($tempEnv, $envContent);

    $this->mock(VaultrClient::class, function ($mock) {
        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [['slug' => 'testing']]]);

        // Should only be called for APP_NAME and DB_PASSWORD
        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::on(function ($name) {
                return ! in_array($name, ['COMMENTED_VAR', 'PUSH_ME']);
            }), Mockery::any())
            ->andReturn([]);
    });

    // Create a dummy key file
    $homeDir = sys_get_temp_dir().'/vaultr_test_home_commented';
    if (! file_exists($homeDir)) {
        mkdir($homeDir, 0777, true);
    }
    $keysFile = $homeDir.'/.vaultr/keys.json';
    if (! file_exists(dirname($keysFile))) {
        mkdir(dirname($keysFile), 0777, true);
    }

    $fakeKey = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'; // 32 bytes
    File::put($keysFile, json_encode(['testing' => CryptoHelper::base64urlEncode($fakeKey)]));

    // Set HOME env var for the command to find the keys
    $oldHome = $_SERVER['HOME'] ?? null;
    $_SERVER['HOME'] = $homeDir;

    // Act & Assert
    $this->artisan("vaultr:variables push --application=app_123 --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Push 2 variable(s) to your Vaultr application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    // Cleanup
    unlink($tempEnv);
    File::deleteDirectory($homeDir);
    if ($oldHome) {
        $_SERVER['HOME'] = $oldHome;
    } else {
        unset($_SERVER['HOME']);
    }
});

it('skips variables with inline comments if they are at the start of the line', function () {
    // Arrange
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    $envContent = <<<'EOD'
  # LEADING_SPACE_COMMENT=value
NOT_COMMENTED=true # INLINE_COMMENT=not_a_var
WITH_HASH="value#with#hash"
EOD;
    File::put($tempEnv, $envContent);

    $this->mock(VaultrClient::class, function ($mock) {
        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [['slug' => 'testing']]]);

        // Should only be called for NOT_COMMENTED and WITH_HASH
        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::on(function ($name) {
                return in_array($name, ['NOT_COMMENTED', 'WITH_HASH']);
            }), Mockery::any())
            ->andReturn([]);
    });

    // Create a dummy key file
    $homeDir = sys_get_temp_dir().'/vaultr_test_home_commented_2';
    if (! file_exists($homeDir)) {
        mkdir($homeDir, 0777, true);
    }
    $keysFile = $homeDir.'/.vaultr/keys.json';
    if (! file_exists(dirname($keysFile))) {
        mkdir(dirname($keysFile), 0777, true);
    }

    $fakeKey = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'; // 32 bytes
    File::put($keysFile, json_encode(['testing' => CryptoHelper::base64urlEncode($fakeKey)]));

    // Set HOME env var for the command to find the keys
    $oldHome = $_SERVER['HOME'] ?? null;
    $_SERVER['HOME'] = $homeDir;

    // Act & Assert
    $this->artisan("vaultr:variables push --application=app_123 --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Push 2 variable(s) to your Vaultr application?', true)
        ->expectsOutputToContain('Push completed!')
        ->expectsOutputToContain('Created or Updated: 2')
        ->assertSuccessful();

    // Cleanup
    unlink($tempEnv);
    File::deleteDirectory($homeDir);
    if ($oldHome) {
        $_SERVER['HOME'] = $oldHome;
    } else {
        unset($_SERVER['HOME']);
    }
});
