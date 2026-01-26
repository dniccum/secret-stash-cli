<?php

use Dniccum\Vaultr\Crypto\CryptoHelper;
use Dniccum\Vaultr\VaultrClient;
use Illuminate\Support\Facades\File;

it('skips variables with VAULTR_ prefix when pushing', function () {
    // Arrange
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, "APP_NAME=VaultrApp\nVAULTR_API_TOKEN=secret_token\nDB_PASSWORD=password123\nVAULTR_URL=https://vaultr.io");

    // Create a dummy user key file
    $homeDir = sys_get_temp_dir().'/vaultr_test_home';
    if (! file_exists($homeDir)) {
        mkdir($homeDir, 0777, true);
    }
    $keysFile = $homeDir.'/.vaultr/user_key.json';
    if (! file_exists(dirname($keysFile))) {
        mkdir(dirname($keysFile), 0777, true);
    }

    // Generate real RSA key pair for testing
    $res = openssl_pkey_new([
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($res, $privateKey);
    $publicKeyDetails = openssl_pkey_get_details($res);
    $publicKey = $publicKeyDetails["key"];

    $password = "password";
    $payload = CryptoHelper::encryptPrivateKey($privateKey, $password);
    File::put($keysFile, json_encode($payload));

    $this->mock(VaultrClient::class, function ($mock) use ($publicKey) {
        $mock->shouldReceive('getEnvironments')
            ->twice()
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123']]]);

        // Generate a real envelope using the public key
        $dek = str_repeat('a', 32);
        $envelope = CryptoHelper::createEnvelope($dek, $publicKey);

        $mock->shouldReceive('getEnvironmentEnvelope')
            ->with('env_123')
            ->once()
            ->andReturn(['data' => ['envelope' => $envelope]]);

        // Should only be called for APP_NAME and DB_PASSWORD
        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::on(function ($name) {
                return ! str_starts_with($name, 'VAULTR_');
            }), Mockery::any())
            ->andReturn([]);
    });

    // Set HOME env var for the command to find the keys
    $oldHome = $_SERVER['HOME'] ?? null;
    $_SERVER['HOME'] = $homeDir;

    // Act & Assert
    $this->artisan("vaultr:variables push --application=app_123 --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Enter your private key password', 'password')
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

    // Create a dummy user key file
    $homeDir = sys_get_temp_dir().'/vaultr_test_home_commented';
    if (! file_exists($homeDir)) {
        mkdir($homeDir, 0777, true);
    }
    $keysFile = $homeDir.'/.vaultr/user_key.json';
    if (! file_exists(dirname($keysFile))) {
        mkdir(dirname($keysFile), 0777, true);
    }

    // Generate real RSA key pair for testing
    $res = openssl_pkey_new([
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($res, $privateKey);
    $publicKeyDetails = openssl_pkey_get_details($res);
    $publicKey = $publicKeyDetails["key"];

    $password = "password";
    $payload = CryptoHelper::encryptPrivateKey($privateKey, $password);
    File::put($keysFile, json_encode($payload));

    $this->mock(VaultrClient::class, function ($mock) use ($publicKey) {
        $mock->shouldReceive('getEnvironments')
            ->twice()
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123']]]);

        // Generate a real envelope using the public key
        $dek = str_repeat('a', 32);
        $envelope = CryptoHelper::createEnvelope($dek, $publicKey);

        $mock->shouldReceive('getEnvironmentEnvelope')
            ->with('env_123')
            ->once()
            ->andReturn(['data' => ['envelope' => $envelope]]);

        // Should only be called for APP_NAME and DB_PASSWORD
        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::on(function ($name) {
                return ! in_array($name, ['COMMENTED_VAR', 'PUSH_ME']);
            }), Mockery::any())
            ->andReturn([]);
    });

    // Set HOME env var for the command to find the keys
    $oldHome = $_SERVER['HOME'] ?? null;
    $_SERVER['HOME'] = $homeDir;

    // Act & Assert
    $this->artisan("vaultr:variables push --application=app_123 --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Enter your private key password', 'password')
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

    // Create a dummy key file
    $homeDir = sys_get_temp_dir().'/vaultr_test_home_commented_2';
    if (! file_exists($homeDir)) {
        mkdir($homeDir, 0777, true);
    }
    $keysFile = $homeDir.'/.vaultr/user_key.json';
    if (! file_exists(dirname($keysFile))) {
        mkdir(dirname($keysFile), 0777, true);
    }

    // Generate real RSA key pair for testing
    $res = openssl_pkey_new([
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($res, $privateKey);
    $publicKeyDetails = openssl_pkey_get_details($res);
    $publicKey = $publicKeyDetails["key"];

    $password = "password";
    $payload = CryptoHelper::encryptPrivateKey($privateKey, $password);
    File::put($keysFile, json_encode($payload));

    $this->mock(VaultrClient::class, function ($mock) use ($publicKey) {
        $mock->shouldReceive('getEnvironments')
            ->twice()
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123']]]);

        // Generate a real envelope using the public key
        $dek = str_repeat('a', 32);
        $envelope = CryptoHelper::createEnvelope($dek, $publicKey);

        $mock->shouldReceive('getEnvironmentEnvelope')
            ->with('env_123')
            ->once()
            ->andReturn(['data' => ['envelope' => $envelope]]);

        // Should only be called for NOT_COMMENTED and WITH_HASH
        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::on(function ($name) {
                return in_array($name, ['NOT_COMMENTED', 'WITH_HASH']);
            }), Mockery::any())
            ->andReturn([]);
    });

    // Set HOME env var for the command to find the keys
    $oldHome = $_SERVER['HOME'] ?? null;
    $_SERVER['HOME'] = $homeDir;

    // Act & Assert
    $this->artisan("vaultr:variables push --application=app_123 --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Enter your private key password', 'password')
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

it('skips variables defined in ignored_variables config when pushing', function () {
    // Arrange
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, "APP_NAME=VaultrApp\nIGNORE_ME=true\nDB_PASSWORD=password123");

    // Mock config
    config(['vaultr.ignored_variables' => ['IGNORE_ME']]);

    // Create a dummy key file
    $homeDir = sys_get_temp_dir().'/vaultr_test_home_ignored';
    if (! file_exists($homeDir)) {
        mkdir($homeDir, 0777, true);
    }
    $keysFile = $homeDir.'/.vaultr/user_key.json';
    if (! file_exists(dirname($keysFile))) {
        mkdir(dirname($keysFile), 0777, true);
    }

    // Generate real RSA key pair for testing
    $res = openssl_pkey_new([
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($res, $privateKey);
    $publicKeyDetails = openssl_pkey_get_details($res);
    $publicKey = $publicKeyDetails["key"];

    $password = "password";
    $payload = CryptoHelper::encryptPrivateKey($privateKey, $password);
    File::put($keysFile, json_encode($payload));

    $this->mock(VaultrClient::class, function ($mock) use ($publicKey) {
        $mock->shouldReceive('getEnvironments')
            ->twice()
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123']]]);

        // Generate a real envelope using the public key
        $dek = str_repeat('a', 32);
        $envelope = CryptoHelper::createEnvelope($dek, $publicKey);

        $mock->shouldReceive('getEnvironmentEnvelope')
            ->with('env_123')
            ->once()
            ->andReturn(['data' => ['envelope' => $envelope]]);

        // Should only be called for APP_NAME and DB_PASSWORD
        $mock->shouldReceive('createVariable')
            ->twice()
            ->with('app_123', 'testing', Mockery::on(function ($name) {
                return $name !== 'IGNORE_ME';
            }), Mockery::any())
            ->andReturn([]);
    });

    $oldHome = $_SERVER['HOME'] ?? null;
    $_SERVER['HOME'] = $homeDir;

    // Act & Assert
    $this->artisan("vaultr:variables push --application=app_123 --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Enter your private key password', 'password')
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

it('skips variables defined in ignored_variables config when pulling', function () {
    // Arrange
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, 'EXISTING_VAR=old_value');

    // Mock config
    config(['vaultr.ignored_variables' => ['IGNORE_ME_TOO']]);

    // Create a dummy key file
    $homeDir = sys_get_temp_dir().'/vaultr_test_home_pull';
    if (! file_exists($homeDir)) {
        mkdir($homeDir, 0777, true);
    }
    $keysFile = $homeDir.'/.vaultr/user_key.json';
    if (! file_exists(dirname($keysFile))) {
        mkdir(dirname($keysFile), 0777, true);
    }

    // Generate real RSA key pair for testing
    $res = openssl_pkey_new([
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($res, $privateKey);
    $publicKeyDetails = openssl_pkey_get_details($res);
    $publicKey = $publicKeyDetails["key"];

    $password = "password";
    $payload = CryptoHelper::encryptPrivateKey($privateKey, $password);
    File::put($keysFile, json_encode($payload));

    $this->mock(VaultrClient::class, function ($mock) use ($publicKey) {
        $mock->makePartial();
        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn(['data' => [['slug' => 'testing', 'id' => 'env_123']]]);

        // Generate a real envelope using the public key
        $dek = str_repeat('a', 32);
        $envelope = CryptoHelper::createEnvelope($dek, $publicKey);

        $mock->shouldReceive('getEnvironmentEnvelope')
            ->with('env_123')
            ->once()
            ->andReturn(['data' => ['envelope' => $envelope]]);

        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    ['name' => 'NEW_VAR', 'payload' => ['value' => 'added_value']],
                    ['name' => 'IGNORE_ME_TOO', 'payload' => ['value' => 'should_not_be_here']],
                ],
            ]);
    });

    $oldHome = $_SERVER['HOME'] ?? null;
    $_SERVER['HOME'] = $homeDir;

    // Act & Assert
    $this->artisan("vaultr:variables pull --application=app_123 --environment=testing --file={$tempEnv}")
        ->expectsQuestion('Enter your private key password', 'password')
        ->expectsOutputToContain('Variables pulled successfully!')
        ->assertSuccessful();

    $content = File::get($tempEnv);
    expect($content)->toContain('NEW_VAR=added_value')
        ->not->toContain('IGNORE_ME_TOO');

    // Cleanup
    unlink($tempEnv);
    File::deleteDirectory($homeDir);
    if ($oldHome) {
        $_SERVER['HOME'] = $oldHome;
    } else {
        unset($_SERVER['HOME']);
    }
});
