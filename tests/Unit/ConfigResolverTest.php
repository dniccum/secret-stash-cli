<?php

use Dniccum\SecretStash\Support\ConfigResolver;

beforeEach(function () {
    ConfigResolver::clearCache();
    // Clean up any env vars set during tests
    putenv('SECRET_STASH_API_TOKEN');
    putenv('SECRET_STASH_API_URL');
    putenv('SECRET_STASH_APPLICATION_ID');
});

afterEach(function () {
    ConfigResolver::clearCache();
    putenv('SECRET_STASH_API_TOKEN');
    putenv('SECRET_STASH_API_URL');
    putenv('SECRET_STASH_APPLICATION_ID');
});

it('resolves config from environment variables', function () {
    putenv('SECRET_STASH_API_TOKEN=env-token-123');

    expect(ConfigResolver::get('api_token'))->toBe('env-token-123');
});

it('falls back to default values when no env or .env is set', function () {
    expect(ConfigResolver::get('api_url'))->toBe('https://secretstash.cloud');
});

it('returns provided default when no value is found', function () {
    expect(ConfigResolver::get('nonexistent_key', 'fallback'))->toBe('fallback');
});

it('returns null when no value or default is provided', function () {
    expect(ConfigResolver::get('nonexistent_key'))->toBeNull();
});

it('prioritizes environment variables over defaults', function () {
    putenv('SECRET_STASH_API_URL=https://custom.example.com');

    expect(ConfigResolver::get('api_url'))->toBe('https://custom.example.com');
});

it('returns default ignored variables', function () {
    $ignored = ConfigResolver::ignoredVariables();

    expect($ignored)->toBe(['APP_KEY', 'APP_ENV']);
});

it('clears cache correctly', function () {
    // Populate cache by resolving a value
    ConfigResolver::get('api_url');

    // Clear and verify it can be repopulated
    ConfigResolver::clearCache();

    expect(ConfigResolver::get('api_url'))->toBe('https://secretstash.cloud');
});

it('strips double quotes from .env values', function () {
    $tmpDir = sys_get_temp_dir().'/config-resolver-test-'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/.env', 'SECRET_STASH_API_TOKEN="my-quoted-token"'."\n");

    $originalCwd = getcwd();
    chdir($tmpDir);
    ConfigResolver::clearCache();

    try {
        $value = ConfigResolver::get('api_token');
        expect($value)->toBe('my-quoted-token');
    } finally {
        chdir($originalCwd);
        unlink($tmpDir.'/.env');
        rmdir($tmpDir);
    }
});

it('strips single quotes from .env values', function () {
    $tmpDir = sys_get_temp_dir().'/config-resolver-test-'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/.env', "SECRET_STASH_API_TOKEN='single-quoted-token'\n");

    $originalCwd = getcwd();
    chdir($tmpDir);
    ConfigResolver::clearCache();

    try {
        $value = ConfigResolver::get('api_token');
        expect($value)->toBe('single-quoted-token');
    } finally {
        chdir($originalCwd);
        unlink($tmpDir.'/.env');
        rmdir($tmpDir);
    }
});

it('reads unquoted .env values correctly', function () {
    $tmpDir = sys_get_temp_dir().'/config-resolver-test-'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/.env', "SECRET_STASH_API_TOKEN=plain-token\n");

    $originalCwd = getcwd();
    chdir($tmpDir);
    ConfigResolver::clearCache();

    try {
        $value = ConfigResolver::get('api_token');
        expect($value)->toBe('plain-token');
    } finally {
        chdir($originalCwd);
        unlink($tmpDir.'/.env');
        rmdir($tmpDir);
    }
});

it('prioritizes env vars over .env file values', function () {
    $tmpDir = sys_get_temp_dir().'/config-resolver-test-'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/.env', "SECRET_STASH_API_TOKEN=dotenv-token\n");

    $originalCwd = getcwd();
    chdir($tmpDir);
    ConfigResolver::clearCache();
    putenv('SECRET_STASH_API_TOKEN=system-env-token');

    try {
        $value = ConfigResolver::get('api_token');
        expect($value)->toBe('system-env-token');
    } finally {
        chdir($originalCwd);
        putenv('SECRET_STASH_API_TOKEN');
        unlink($tmpDir.'/.env');
        rmdir($tmpDir);
    }
});
