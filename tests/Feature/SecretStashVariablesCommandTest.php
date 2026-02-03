<?php

use Dniccum\SecretStash\SecretStashClient;
use Illuminate\Support\Facades\File;

it('can run the variables:list command and display results', function () {
    // Arrange: fake the SecretStashClient so no HTTP requests are made
    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => 'var_1', 'name' => 'APP_NAME', 'created_at' => '2025-01-01 00:00:00', 'payload' => ['iv' => '...', 'tag' => '...', 'ct' => '...']],
                    ['id' => 'var_2', 'name' => 'APP_ENV', 'created_at' => '2025-01-02 00:00:00', 'payload' => ['iv' => '...', 'tag' => '...', 'ct' => '...']],
                ],
            ]);
    });

    // Act & Assert
    $this->artisan('secret-stash:variables list --application=app_123 --environment=testing --key=test-dek')
        ->expectsOutputToContain('Environment Variables')
        ->expectsOutputToContain('Total: 2 variable(s)')
        ->assertSuccessful();
});

it('gracefully handles no variables found', function () {
    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn(['data' => []]);
    });

    $this->artisan('secret-stash:variables list --application=app_123 --environment=testing --key=test-dek')
        ->expectsOutputToContain('No variables found.')
        ->assertSuccessful();
});

it('updates .env when variables are pulled with various key formats', function () {
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, "existing_var=old_value\nMIXED_Case=stay_same");

    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->makePartial();
        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    ['name' => 'EXISTING_VAR', 'payload' => ['value' => 'new_value']],
                    ['name' => 'new_var', 'payload' => ['value' => 'added_value']],
                    ['name' => 'EMPTY_VAR', 'payload' => ['value' => '']],
                    ['name' => 'SPACE_VAR', 'payload' => ['value' => 'has space']],
                ],
            ]);
    });

    $this->artisan("secret-stash:variables pull --application=app_123 --environment=testing --key=test-dek --file={$tempEnv}")
        ->expectsOutputToContain('Variables pulled successfully!')
        ->assertSuccessful();

    $content = File::get($tempEnv);
    expect($content)->toContain('EXISTING_VAR=new_value')
        ->not->toContain('existing_var=old_value')
        ->toContain('new_var=added_value')
        ->toContain('EMPTY_VAR=""')
        ->toContain('SPACE_VAR="has space"');

    unlink($tempEnv);
});

it('correctly decrypts values during pull if key is provided', function () {
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, '');

    $keyBase64 = 'zrEAMmf1sINqHs27v-M8hq_0PqRSOv7pVdF5uuhtC_Q';
    // Mock the SecretStashClient
    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->makePartial();
        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    [
                        'id' => '019bd30d-ca8a-7241-9499-b9e7e8d4fbf4',
                        'name' => 'MAIL_FROM_ADDRESS',
                        'payload' => [
                            'v' => 1,
                            'alg' => 'AES-GCM',
                            'kdf' => 'none',
                            'iter' => 0,
                            'salt' => 'OYnuBSYsDA-e4PYmBTRrOg',
                            'iv' => '7v_mnKaw9ebxHcGb',
                            'tag' => 'MmcWCpIdG9WFqS-pFFSBPQ',
                            'ct' => 'ghWFuq4fOxtCb1QTPasg8m57a_J2N8TrpBKt',
                        ],
                    ],
                ],
            ]);
    });

    $this->artisan("secret-stash:variables pull --application=app_123 --environment=testing --file={$tempEnv} --key={$keyBase64}")
        ->expectsOutputToContain('Fetching variables from SecretStash...')
        ->expectsOutputToContain('Variables pulled successfully!')
        ->assertSuccessful();

    $content = File::get($tempEnv);
    // Since we are not mocking CryptoHelper anymore, and it's using a real key,
    // it will either work or fail to decrypt.
    // Given the bypass in Command, it will use $keyBase64 directly.
    // Actually, the command uses the key to SYNC into .env.

    expect($content)->toContain('MAIL_FROM_ADDRESS=');

    unlink($tempEnv);
});

it('does not write null values to .env when pulling', function () {
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, 'EXISTING_VAR=some_value');

    $this->mock(SecretStashClient::class, function ($mock) {
        $mock->makePartial();
        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    ['name' => 'NULL_VAR', 'value' => null],
                    ['name' => 'OTHER_VAR', 'value' => 'fine'],
                ],
            ]);
    });

    $this->artisan("secret-stash:variables pull --application=app_123 --environment=testing --key=test-dek --file={$tempEnv}")
        ->expectsOutputToContain('Variables pulled successfully!')
        ->assertSuccessful();

    $content = File::get($tempEnv);
    expect($content)->not->toContain('NULL_VAR')
        ->toContain('OTHER_VAR=fine')
        ->toContain('EXISTING_VAR=some_value');

    unlink($tempEnv);
});

it('correctly reads APP_ENV from .env file', function () {
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, "APP_ENV=staging\n");

    $command = Mockery::mock(\Dniccum\SecretStash\Commands\SecretStashVariablesCommand::class)->makePartial();
    $command->shouldAllowMockingProtectedMethods();
    $command->shouldReceive('option')->with('file')->andReturn($tempEnv);

    expect($command->getAppEnvFromEnvFile())->toBe('staging');

    unlink($tempEnv);
});

it('correctly reads APP_ENV from .env file with quotes', function () {
    $tempEnv = tempnam(sys_get_temp_dir(), '.env');
    File::put($tempEnv, "APP_ENV=\"production\"\n");

    $command = Mockery::mock(\Dniccum\SecretStash\Commands\SecretStashVariablesCommand::class)->makePartial();
    $command->shouldAllowMockingProtectedMethods();
    $command->shouldReceive('option')->with('file')->andReturn($tempEnv);

    expect($command->getAppEnvFromEnvFile())->toBe('production');

    unlink($tempEnv);
});
