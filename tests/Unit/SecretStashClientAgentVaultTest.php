<?php

use Dniccum\SecretStash\Enums\AgentType;
use Dniccum\SecretStash\SecretStashClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

function createAgentVaultMockClient(array $responses): Client
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);

    return new Client(['handler' => $handlerStack]);
}

function buildAgentVaultClient(array $responses, ?string $apiVersion = null): SecretStashClient
{
    $client = new SecretStashClient('https://secret-stash.app', 'test-token', null, $apiVersion);
    $client->setHttpClient(createAgentVaultMockClient($responses));

    return $client;
}

it('defaults to a versioned /api/v1/ base uri', function () {
    $client = new SecretStashClient('https://secret-stash.app', 'test-token');

    $reflection = new ReflectionMethod($client, 'buildClient');
    $httpClient = $reflection->invoke($client);

    expect((string) $httpClient->getConfig('base_uri'))->toBe('https://secret-stash.app/api/v1/');
});

it('allows overriding the api version', function () {
    $client = new SecretStashClient('https://secret-stash.app', 'test-token', apiVersion: 'v2');

    $reflection = new ReflectionMethod($client, 'buildClient');
    $httpClient = $reflection->invoke($client);

    expect((string) $httpClient->getConfig('base_uri'))->toBe('https://secret-stash.app/api/v2/');
});

it('falls back to the legacy unversioned base uri when api version is empty', function () {
    $client = new SecretStashClient('https://secret-stash.app', 'test-token', apiVersion: '');

    $reflection = new ReflectionMethod($client, 'buildClient');
    $httpClient = $reflection->invoke($client);

    expect((string) $httpClient->getConfig('base_uri'))->toBe('https://secret-stash.app/api/');
});

it('lists agents', function () {
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => [['id' => 'agent-1', 'name' => 'CI Bot']]])),
    ]);

    expect($client->getAgents())->toBe(['data' => [['id' => 'agent-1', 'name' => 'CI Bot']]]);
});

it('shows a single agent', function () {
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['id' => 'agent-1', 'name' => 'CI Bot']])),
    ]);

    expect($client->getAgent('agent-1'))->toBe(['data' => ['id' => 'agent-1', 'name' => 'CI Bot']]);
});

it('creates an agent using an AgentType enum and returns the one-time token', function () {
    $client = buildAgentVaultClient([
        new Response(201, [], json_encode([
            'data' => ['id' => 'agent-1', 'name' => 'CI Bot', 'type' => 'claude_code'],
            'token' => 'plaintext-one-time-token',
        ])),
    ]);

    $result = $client->createAgent(AgentType::ClaudeCode, 'CI Bot', 'Used in CI pipelines');

    expect($result['token'])->toBe('plaintext-one-time-token')
        ->and($result['data']['type'])->toBe('claude_code');
});

it('creates an agent using a plain string type', function () {
    $client = buildAgentVaultClient([
        new Response(201, [], json_encode(['data' => ['id' => 'agent-1', 'type' => 'custom']])),
    ]);

    $result = $client->createAgent('custom', 'Custom Agent');

    expect($result['data']['type'])->toBe('custom');
});

it('updates an agent', function () {
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['id' => 'agent-1', 'name' => 'Renamed Bot']])),
    ]);

    expect($client->updateAgent('agent-1', ['name' => 'Renamed Bot']))
        ->toBe(['data' => ['id' => 'agent-1', 'name' => 'Renamed Bot']]);
});

it('deletes an agent', function () {
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['message' => 'Agent revoked.'])),
    ]);

    expect($client->deleteAgent('agent-1'))->toBe(['message' => 'Agent revoked.']);
});

it('syncs agent environments', function () {
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['environments' => ['env-1', 'env-2']]])),
    ]);

    expect($client->syncAgentEnvironments('agent-1', ['env-1', 'env-2']))
        ->toBe(['data' => ['environments' => ['env-1', 'env-2']]]);
});

it('provisions an agent environment dek', function () {
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['agent_id' => 'agent-1', 'environment_id' => 'env-1']])),
    ]);

    expect($client->provisionAgentEnvironmentDek('agent-1', 'env-1', 'sealed-dek-base64'))
        ->toBe(['data' => ['agent_id' => 'agent-1', 'environment_id' => 'env-1']]);
});

it('lists agent secrets', function () {
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => [['name' => 'DB_PASSWORD']]])),
    ]);

    expect($client->getAgentSecrets('agent-1'))->toBe(['data' => [['name' => 'DB_PASSWORD']]]);
});

it('shows a single agent secret', function () {
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['name' => 'DB_PASSWORD']])),
    ]);

    expect($client->getAgentSecret('agent-1', 'secret-1'))->toBe(['data' => ['name' => 'DB_PASSWORD']]);
});

it('batch resolves agent secrets', function () {
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['DB_PASSWORD' => 'hunter2']])),
    ]);

    $result = $client->resolveAgentSecrets('agent-1', ['DB_PASSWORD'], 'env-1');

    expect($result)->toBe(['data' => ['DB_PASSWORD' => 'hunter2']]);
});

it('tests an agent connection', function () {
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode([
            'status' => 'pass',
            'checks' => [
                ['name' => 'token', 'passed' => true, 'message' => 'Token is valid.'],
            ],
        ])),
    ]);

    $result = $client->testAgent('agent-1');

    expect($result['status'])->toBe('pass');
});
