<?php

use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\Support\VariableUtility;

it('filters ignored variables and secret stash prefix', function () {
    $variables = [
        'APP_KEY' => 'base64:abc',
        'SECRET_STASH_TOKEN' => 'nope',
        'IGNORED_ONE' => 'skip',
    ];

    $filtered = VariableUtility::filterVariables($variables, ['IGNORED_ONE']);

    expect($filtered)->toBe([
        'APP_KEY' => 'base64:abc',
    ]);
});

it('merges env content by updating existing and appending new variables', function () {
    $content = "APP_NAME=Old\nEXISTING=1\n# Comment\n";
    $variables = [
        'EXISTING' => '2',
        'NEW' => '3',
    ];

    $merged = VariableUtility::mergeEnvContent($content, $variables);

    expect($merged)->toContain("APP_NAME=Old\n")
        ->and($merged)->toContain("EXISTING=2\n")
        ->and($merged)->toContain("# Comment\n");

    $lines = array_values(array_filter(explode("\n", trim($merged)), 'strlen'));
    expect(end($lines))->toBe('NEW=3');
});

it('parses env variables with empty values', function () {
    $content = "APP_NAME=MyApp\nAPP_KEY=\nDB_HOST=localhost\n";

    $parsed = VariableUtility::parseEnvContent($content);

    expect($parsed)->toBe([
        'APP_NAME' => 'MyApp',
        'APP_KEY' => '',
        'DB_HOST' => 'localhost',
    ]);
});

it('encrypts and decrypts an empty string value', function () {
    $key = CryptoHelper::generateKey();

    $payload = CryptoHelper::aesGcmEncrypt('', $key);

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['v', 'alg', 'kdf', 'iter', 'salt', 'iv', 'tag', 'ct'])
        ->and($payload['ct'])->toBeString();

    $decrypted = CryptoHelper::aesGcmDecrypt($payload, $key);

    expect($decrypted)->toBe('');
});

it('merges env content preserving empty values', function () {
    $content = "APP_NAME=Old\nAPP_KEY=\n";
    $variables = [
        'APP_KEY' => '',
        'NEW_EMPTY' => '',
    ];

    $merged = VariableUtility::mergeEnvContent($content, $variables);

    expect($merged)->toContain('APP_KEY=')
        ->and($merged)->toContain('NEW_EMPTY=');
});
