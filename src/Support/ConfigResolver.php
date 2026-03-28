<?php

namespace Dniccum\SecretStash\Support;

/**
 * Framework-agnostic configuration resolver.
 *
 * Resolution order:
 * 1. System environment variables (highest priority)
 * 2. .env file in the current working directory
 * 3. Laravel config() helper (when running inside Laravel)
 * 4. Default values
 */
class ConfigResolver
{
    /**
     * Mapping from config keys to environment variable names.
     */
    protected static array $envMap = [
        'api_token' => 'SECRET_STASH_API_TOKEN',
        'api_url' => 'SECRET_STASH_API_URL',
        'application_id' => 'SECRET_STASH_APPLICATION_ID',
        'key_dir' => 'SECRET_STASH_KEY_DIR',
        'app_env' => 'APP_ENV',
    ];

    /**
     * Default configuration values.
     */
    protected static array $defaults = [
        'api_url' => 'https://secretstash.cloud',
        'ignored_variables' => ['APP_KEY', 'APP_ENV'],
    ];

    /**
     * Cached .env values from the project root.
     *
     * @var array<string, string>|null
     */
    protected static ?array $dotenvCache = null;

    /**
     * Resolve a configuration value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // 1. Check system environment variables
        $envKey = static::$envMap[$key] ?? null;
        if ($envKey) {
            $envValue = getenv($envKey);
            if ($envValue !== false && $envValue !== '') {
                return $envValue;
            }
        }

        // 2. Check .env file in project root
        if ($envKey) {
            $dotenv = static::loadDotenv();
            if (isset($dotenv[$envKey])) {
                return $dotenv[$envKey];
            }
        }

        // 3. Try Laravel's config() helper if available
        $laravelValue = static::fromLaravel($key);
        if ($laravelValue !== null) {
            return $laravelValue;
        }

        // 4. Fall back to defaults, then provided default
        return static::$defaults[$key] ?? $default;
    }

    /**
     * Get the ignored variables list.
     *
     * @return array<int, string>
     */
    public static function ignoredVariables(): array
    {
        // Try Laravel config first
        $laravelIgnored = static::fromLaravel('ignored_variables');
        if (is_array($laravelIgnored)) {
            return array_values($laravelIgnored);
        }

        return static::$defaults['ignored_variables'] ?? [];
    }

    /**
     * Check whether the code is running inside a Laravel application.
     */
    public static function isLaravel(): bool
    {
        return function_exists('app') && app()->bound('config');
    }

    /**
     * Check whether the code is running inside a unit test.
     */
    public static function isRunningTests(): bool
    {
        if (function_exists('app')) {
            try {
                return app()->runningUnitTests();
            } catch (\Throwable) {
                // Container not booted or method unavailable
            }
        }

        // Fallback: check common test runner environment indicators
        return (bool) getenv('PEST_RUNNING')
            || (bool) getenv('PHPUNIT_RUNNING')
            || defined('PEST_RUNNING')
            || defined('PHPUNIT_COMPOSER_INSTALL');
    }

    /**
     * Attempt to read a value from Laravel's config system.
     */
    protected static function fromLaravel(string $key): mixed
    {
        if (! static::isLaravel()) {
            return null;
        }

        try {
            $configKey = match ($key) {
                'app_env' => 'app.env',
                default => "secret-stash.{$key}",
            };

            return app()->make('config')->get($configKey);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Load and cache .env file from the current working directory.
     *
     * @return array<string, string>
     */
    protected static function loadDotenv(): array
    {
        if (static::$dotenvCache !== null) {
            return static::$dotenvCache;
        }

        static::$dotenvCache = [];

        $envPath = getcwd().'/.env';
        if (! file_exists($envPath)) {
            return static::$dotenvCache;
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            return static::$dotenvCache;
        }

        static::$dotenvCache = VariableUtility::parseEnvContent($content);

        return static::$dotenvCache;
    }

    /**
     * Clear the cached .env values (useful for testing).
     */
    public static function clearCache(): void
    {
        static::$dotenvCache = null;
    }
}
