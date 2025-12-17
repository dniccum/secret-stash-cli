<?php

use Dniccum\Vaultr\VaultrClient;

it('can run the variables:list command and display results', function () {
    // Arrange: fake the VaultrClient so no HTTP requests are made
    $this->mock(VaultrClient::class, function ($mock) {
        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => 'var_1', 'name' => 'APP_NAME', 'created_at' => '2025-01-01 00:00:00'],
                    ['id' => 'var_2', 'name' => 'APP_ENV', 'created_at' => '2025-01-02 00:00:00'],
                ],
            ]);
    });

    // Act & Assert
    $this->artisan('vaultr:variables list --application=app_123 --environment=testing')
        ->expectsOutputToContain('Environment Variables')
        ->expectsPromptsTable(
            headers: ['Name'],
            rows: [
                ['APP_NAME'],
                ['APP_ENV'],
            ],
        )
        ->expectsOutputToContain('Total: 2 variable(s)')
        ->assertSuccessful();
});

it('gracefully handles no variables found', function () {
    $this->mock(VaultrClient::class, function ($mock) {
        $mock->shouldReceive('getVariables')
            ->once()
            ->andReturn(['data' => []]);
    });

    $this->artisan('vaultr:variables list --application=app_123 --environment=testing')
        ->expectsOutputToContain('No variables found.')
        ->assertSuccessful();
});
