<?php

namespace Dniccum\Vaultr\Tests;

use Dniccum\Vaultr\VaultrServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Dniccum\\Vaultr\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            VaultrServiceProvider::class,
        ];
    }

    /**
     * Configure the application environment for tests.
     */
    protected function defineEnvironment($app): void
    {
        // Load package config so keys exist, then override the ones we need for commands
        $app['config']->set('vaultr', require __DIR__.'/../config/vaultr.php');

        // Provide a default application id so commands don't prompt
        $app['config']->set('vaultr.default_application_id', 'app_123');

        // Typical framework config used by console testing
        $app['config']->set('app.env', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
