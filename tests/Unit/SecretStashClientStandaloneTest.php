<?php

use Dniccum\SecretStash\Exceptions\ApiToken\InvalidApiToken;
use Dniccum\SecretStash\SecretStashClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

function createStandaloneMockClient(array $responses): Client
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);

    return new Client(['handler' => $handlerStack]);
}

it('makes successful GET requests and returns decoded JSON', function () {
    $mockClient = createStandaloneMockClient([
        new Response(200, [], json_encode(['data' => ['id' => 1, 'name' => 'Test']])),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $client->setHttpClient($mockClient);

    $result = $client->get('applications');

    expect($result)->toBe(['data' => ['id' => 1, 'name' => 'Test']]);
});

it('makes successful POST requests and returns decoded JSON', function () {
    $mockClient = createStandaloneMockClient([
        new Response(200, [], json_encode(['success' => true])),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $client->setHttpClient($mockClient);

    $result = $client->post('variables', ['name' => 'TEST', 'value' => 'abc']);

    expect($result)->toBe(['success' => true]);
});

it('returns empty array for non-JSON response body', function () {
    $mockClient = createStandaloneMockClient([
        new Response(200, [], 'not json'),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $client->setHttpClient($mockClient);

    $result = $client->get('applications');

    expect($result)->toBe([]);
});

it('throws InvalidApiToken for 401 in standalone mode', function () {
    $mockClient = createStandaloneMockClient([
        new RequestException(
            'Unauthorized',
            new Request('GET', 'applications'),
            new Response(401, [], json_encode(['message' => 'Unauthenticated.']))
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $client->setHttpClient($mockClient);

    expect(fn () => $client->get('applications'))
        ->toThrow(InvalidApiToken::class);
});

it('handles connection failures in standalone mode', function () {
    $mockClient = createStandaloneMockClient([
        new ConnectException(
            'Could not resolve host',
            new Request('GET', 'applications')
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $client->setHttpClient($mockClient);

    expect(fn () => $client->get('applications'))
        ->toThrow(RuntimeException::class, 'Unable to connect to the SecretStash API.');
});

it('allows injecting a custom HTTP client', function () {
    $mockClient = createStandaloneMockClient([
        new Response(200, [], json_encode(['injected' => true])),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $result = $client->setHttpClient($mockClient);

    // setHttpClient should return the client instance for chaining
    expect($result)->toBeInstanceOf(SecretStashClient::class);
});
