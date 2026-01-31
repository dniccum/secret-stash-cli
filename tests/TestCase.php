<?php

namespace Dniccum\SecretStash\Tests;

use Dniccum\SecretStash\SecretStashServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Prompts\Prompt;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Prompt::fallbackWhen(true);

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Dniccum\\SecretStash\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            SecretStashServiceProvider::class,
        ];
    }

    /**
     * Configure the application environment for tests.
     */
    protected function defineEnvironment($app): void
    {
        // Load package config so keys exist, then override the ones we need for commands
        $app['config']->set('secret-stash', require __DIR__.'/../config/secret-stash.php');

        // Set mandatory API credentials for tests to avoid InvalidEnvironmentConfiguration
        $app['config']->set('secret-stash.api_url', 'https://secret-stash.app');
        $app['config']->set('secret-stash.api_token', 'test-token');

        // Provide a default application id so commands don't prompt
        $app['config']->set('secret-stash.application_id', 'app_123');

        // Typical framework config used by console testing
        $app['config']->set('app.env', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
