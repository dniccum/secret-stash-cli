<?php

use Dniccum\Vaultr\VaultrClient;

it('can run the environments:list command and display results', function () {
    $this->mock(VaultrClient::class, function ($mock) {
        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => '1', 'name' => 'Testing', 'slug' => 'testing', 'type' => 'testing', 'variables_count' => 6, 'created_at' => '2025-01-01 00:00:00'],
                    ['id' => '2', 'name' => 'Production', 'slug' => 'production', 'type' => 'production', 'variables_count' => 10, 'created_at' => '2025-01-01 00:00:00'],
                ],
            ]);
    });

    // Act & Assert
    $this->artisan('vaultr:environments list')
        ->expectsOutputToContain('Fetching environments...')
        ->expectsOutputToContain('Environments')
        ->expectsPromptsTable(
            headers: ['ID', 'Name', 'Slug', 'Type', 'Variables', 'Created'],
            rows: [
                ['1', 'Testing', 'testing', 'testing', '6', '2025-01-01 00:00:00'],
                ['2', 'Production', 'production', 'production', '10', '2025-01-01 00:00:00'],
            ]
        )
        ->assertSuccessful();
});

it('can create an environment from the environments:create command and show success', function () {
    $this->mock(VaultrClient::class, function ($mock) {
        $mock->shouldReceive('createEnvironment')
            ->once()
            ->andReturn([
                'data' => ['id' => '2', 'name' => 'Production', 'slug' => 'production', 'type' => 'production', 'variables_count' => 0, 'created_at' => '2025-01-01 00:00:00'],
            ]);
    });

    // Act & Assert
    $this->artisan('vaultr:environments create')
        ->expectsQuestion('What is the environment name?', 'Production')
        ->expectsQuestion('What should the environment slug be?', 'production')
        ->expectsQuestion('What type of environment is this?', 'production')
        ->expectsOutputToContain('Creating environment...')
        ->expectsOutputToContain('Name: Production')
        ->expectsOutputToContain('Slug: production')
        ->expectsOutputToContain('Type: production')
        ->expectsOutputToContain('Environment created successfully!')
        ->assertSuccessful();
});

it('throws an error if it cannot find the application based on option', function () {
    $this->mock(VaultrClient::class, function ($mock) {
        $mock->shouldReceive('getEnvironments')
            ->once()
            ->andThrow(new \Exception);
    });

    // Act & Assert
    $this->artisan('vaultr:environments list --application=app_098')
        ->expectsOutputToContain('Fetching environments...')
        ->assertFailed();
});
