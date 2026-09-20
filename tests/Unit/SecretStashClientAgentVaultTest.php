<?php

use Dniccum\SecretStash\Enums\AgentType;
use Dniccum\SecretStash\SecretStashClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Builds a client backed by a mock handler that also records every
 * outgoing request in $history, so tests can assert on the actual
 * HTTP method/path/body sent, not just the mocked response.
 */
function buildAgentVaultClient(array $responses, array &$history = [], ?string $apiVersion = null): SecretStashClient
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));

    $client = new SecretStashClient('https://secret-stash.app', 'test-token', null, $apiVersion);
    $client->setHttpClient(new Client(['handler' => $handlerStack]));

    return $client;
}

function lastRequest(array $history): Request
{
    return end($history)['request'];
}

function decodedBody(Request $request): array
{
    return json_decode((string) $request->getBody(), true) ?? [];
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
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => [['id' => 'agent-1', 'name' => 'CI Bot']]])),
    ], $history);

    $result = $client->getAgents();

    expect($result)->toBe(['data' => [['id' => 'agent-1', 'name' => 'CI Bot']]]);

    $request = lastRequest($history);
    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('agents');
});

it('shows a single agent', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['id' => 'agent-1', 'name' => 'CI Bot']])),
    ], $history);

    $result = $client->getAgent('agent-1');

    expect($result)->toBe(['data' => ['id' => 'agent-1', 'name' => 'CI Bot']]);

    $request = lastRequest($history);
    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('agents/agent-1');
});

it('creates an agent using an AgentType enum and returns the one-time token', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(201, [], json_encode([
            'data' => ['id' => 'agent-1', 'name' => 'CI Bot', 'type' => 'claude_code'],
            'token' => 'plaintext-one-time-token',
        ])),
    ], $history);

    $result = $client->createAgent(AgentType::ClaudeCode, 'CI Bot', 'Used in CI pipelines');

    expect($result['token'])->toBe('plaintext-one-time-token')
        ->and($result['data']['type'])->toBe('claude_code');

    $request = lastRequest($history);
    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('agents')
        ->and(decodedBody($request))->toBe([
            'name' => 'CI Bot',
            'type' => 'claude_code',
            'description' => 'Used in CI pipelines',
        ]);
});

it('creates an agent using a plain string type and omits a null description', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(201, [], json_encode(['data' => ['id' => 'agent-1', 'type' => 'custom']])),
    ], $history);

    $result = $client->createAgent('custom', 'Custom Agent');

    expect($result['data']['type'])->toBe('custom');

    $request = lastRequest($history);
    expect(decodedBody($request))->toBe([
        'name' => 'Custom Agent',
        'type' => 'custom',
    ]);
});

it('updates an agent', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['id' => 'agent-1', 'name' => 'Renamed Bot']])),
    ], $history);

    $result = $client->updateAgent('agent-1', ['name' => 'Renamed Bot']);

    expect($result)->toBe(['data' => ['id' => 'agent-1', 'name' => 'Renamed Bot']]);

    $request = lastRequest($history);
    expect($request->getMethod())->toBe('PUT')
        ->and((string) $request->getUri())->toBe('agents/agent-1')
        ->and(decodedBody($request))->toBe(['name' => 'Renamed Bot']);
});

it('deletes an agent', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['message' => 'Agent revoked.'])),
    ], $history);

    $result = $client->deleteAgent('agent-1');

    expect($result)->toBe(['message' => 'Agent revoked.']);

    $request = lastRequest($history);
    expect($request->getMethod())->toBe('DELETE')
        ->and((string) $request->getUri())->toBe('agents/agent-1');
});

it('syncs agent environments', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['environments' => ['env-1', 'env-2']]])),
    ], $history);

    $result = $client->syncAgentEnvironments('agent-1', ['env-1', 'env-2']);

    expect($result)->toBe(['data' => ['environments' => ['env-1', 'env-2']]]);

    $request = lastRequest($history);
    expect($request->getMethod())->toBe('PUT')
        ->and((string) $request->getUri())->toBe('agents/agent-1/environments')
        ->and(decodedBody($request))->toBe(['environments' => ['env-1', 'env-2']]);
});

it('provisions an agent environment dek', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['agent_id' => 'agent-1', 'environment_id' => 'env-1']])),
    ], $history);

    $result = $client->provisionAgentEnvironmentDek('agent-1', 'env-1', 'sealed-dek-base64');

    expect($result)->toBe(['data' => ['agent_id' => 'agent-1', 'environment_id' => 'env-1']]);

    $request = lastRequest($history);
    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('agents/agent-1/environments/env-1/dek')
        ->and(decodedBody($request))->toBe(['sealed_dek' => 'sealed-dek-base64']);
});

it('lists agent secrets', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => [['name' => 'DB_PASSWORD']]])),
    ], $history);

    $result = $client->getAgentSecrets('agent-1');

    expect($result)->toBe(['data' => [['name' => 'DB_PASSWORD']]]);

    $request = lastRequest($history);
    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('agents/agent-1/secrets');
});

it('lists agent secrets filtered by environment', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => [['name' => 'DB_PASSWORD']]])),
    ], $history);

    $client->getAgentSecrets('agent-1', 'env-1');

    $request = lastRequest($history);
    expect((string) $request->getUri())->toBe('agents/agent-1/secrets?environment=env-1');
});

it('shows a single agent secret', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['name' => 'DB_PASSWORD']])),
    ], $history);

    $result = $client->getAgentSecret('agent-1', 'secret-1');

    expect($result)->toBe(['data' => ['name' => 'DB_PASSWORD']]);

    $request = lastRequest($history);
    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('agents/agent-1/secrets/secret-1');
});

it('batch resolves agent secrets', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['DB_PASSWORD' => 'hunter2']])),
    ], $history);

    $result = $client->resolveAgentSecrets('agent-1', ['DB_PASSWORD'], 'env-1');

    expect($result)->toBe(['data' => ['DB_PASSWORD' => 'hunter2']]);

    $request = lastRequest($history);
    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('agents/agent-1/secrets/resolve')
        ->and(decodedBody($request))->toBe([
            'variables' => ['DB_PASSWORD'],
            'environment' => 'env-1',
        ]);
});

it('batch resolves agent secrets without an environment filter', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode(['data' => ['DB_PASSWORD' => 'hunter2']])),
    ], $history);

    $client->resolveAgentSecrets('agent-1', ['DB_PASSWORD']);

    $request = lastRequest($history);
    expect(decodedBody($request))->toBe(['variables' => ['DB_PASSWORD']]);
});

it('tests an agent connection', function () {
    $history = [];
    $client = buildAgentVaultClient([
        new Response(200, [], json_encode([
            'status' => 'pass',
            'checks' => [
                ['name' => 'token', 'passed' => true, 'message' => 'Token is valid.'],
            ],
        ])),
    ], $history);

    $result = $client->testAgent('agent-1');

    expect($result['status'])->toBe('pass');

    $request = lastRequest($history);
    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('agents/agent-1/test');
});
