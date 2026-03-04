<?php

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
