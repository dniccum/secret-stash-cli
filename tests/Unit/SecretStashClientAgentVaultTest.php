<?php

use Dniccum\SecretStash\SecretStashClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

function agentVaultClient(array &$history, array $responses): SecretStashClient
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return (new SecretStashClient('https://secret-stash.app', 'test-token'))
        ->setHttpClient(new Client(['handler' => $stack, 'base_uri' => 'https://secret-stash.app/api/']));
}

function ok(array $body = []): Response
{
    return new Response(200, [], json_encode($body));
}

it('prefixes endpoints with the v1 version', function () {
    $history = [];
    $client = agentVaultClient($history, []);

    expect($client->versioned('agents'))->toBe('v1/agents')
        ->and($client->versioned('/agents', 'v2'))->toBe('v2/agents');
});

it('routes agent vault calls to versioned endpoints', function (string $method, array $args, string $verb, string $path) {
    $history = [];
    $client = agentVaultClient($history, [ok(['data' => []])]);

    $client->{$method}(...$args);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe($verb)
        ->and($request->getUri()->getPath())->toBe($path);
})->with([
    'list agents' => ['getAgents', [], 'GET', '/api/v1/agents'],
    'show agent' => ['getAgent', ['a1'], 'GET', '/api/v1/agents/a1'],
    'create agent' => ['createAgent', ['bot'], 'POST', '/api/v1/agents'],
    'update agent' => ['updateAgent', ['a1', ['name' => 'x']], 'PUT', '/api/v1/agents/a1'],
    'delete agent' => ['deleteAgent', ['a1'], 'DELETE', '/api/v1/agents/a1'],
    'list agent environments' => ['getAgentEnvironments', ['a1'], 'GET', '/api/v1/agents/a1/environments'],
    'sync environments' => ['syncAgentEnvironments', ['a1', ['e1']], 'PUT', '/api/v1/agents/a1/environments'],
    'provision dek' => ['provisionAgentDek', ['a1', 'e1'], 'POST', '/api/v1/agents/a1/environments/e1/dek'],
    'list secrets' => ['getAgentSecrets', ['a1'], 'GET', '/api/v1/agents/a1/secrets'],
    'show secret' => ['getAgentSecret', ['a1', 'DB_PASSWORD'], 'GET', '/api/v1/agents/a1/secrets/DB_PASSWORD'],
    'resolve secrets' => ['resolveAgentSecrets', ['a1', ['DB_PASSWORD']], 'POST', '/api/v1/agents/a1/secrets'],
    'test agent' => ['testAgent', ['a1'], 'POST', '/api/v1/agents/a1/test'],
]);

it('sends the expected payloads and returns the one-time token on create', function () {
    $history = [];
    $client = agentVaultClient($history, [ok(['data' => ['id' => 'a1'], 'token' => 'once']), ok(), ok()]);

    $created = $client->createAgent('bot', ['description' => 'd']);
    $client->syncAgentEnvironments('a1', ['e1', 'e2']);
    $client->resolveAgentSecrets('a1', ['A', 'B']);

    expect($created['token'])->toBe('once')
        ->and(json_decode((string) $history[0]['request']->getBody(), true))->toBe(['name' => 'bot', 'description' => 'd'])
        ->and(json_decode((string) $history[1]['request']->getBody(), true))->toBe(['environments' => ['e1', 'e2']])
        ->and(json_decode((string) $history[2]['request']->getBody(), true))->toBe(['secrets' => ['A', 'B']]);
});

it('keeps existing endpoints unversioned', function () {
    $history = [];
    $client = agentVaultClient($history, [ok()]);

    $client->getApplications();

    expect($history[0]['request']->getUri()->getPath())->toBe('/api/applications');
});
