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

function createMockClient(array $responses): Client
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);

    return new Client(['handler' => $handlerStack]);
}

it('returns the API message from a JSON error response', function () {
    $mockClient = createMockClient([
        new RequestException(
            'Client error',
            new Request('GET', 'applications'),
            new Response(404, [], json_encode(['message' => 'The "local" environment does not exist for this application.']))
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $client->setHttpClient($mockClient);

    expect(fn () => $client->get('applications'))
        ->toThrow(RuntimeException::class, 'The "local" environment does not exist for this application.');
});

it('falls back to status code when JSON has no message key', function () {
    $mockClient = createMockClient([
        new RequestException(
            'Client error',
            new Request('GET', 'applications'),
            new Response(422, [], json_encode(['error' => 'something went wrong']))
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $client->setHttpClient($mockClient);

    expect(fn () => $client->get('applications'))
        ->toThrow(RuntimeException::class, 'API request failed with status code 422.');
});

it('falls back to status code when response body is not JSON', function () {
    $mockClient = createMockClient([
        new RequestException(
            'Server error',
            new Request('GET', 'applications'),
            new Response(500, [], 'Internal Server Error')
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $client->setHttpClient($mockClient);

    expect(fn () => $client->get('applications'))
        ->toThrow(RuntimeException::class, 'API request failed with status code 500.');
});

it('throws InvalidApiToken for 401 responses', function () {
    $mockClient = createMockClient([
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

it('throws RuntimeException with API message for 403 responses', function () {
    $mockClient = createMockClient([
        new RequestException(
            'Forbidden',
            new Request('GET', 'applications'),
            new Response(403, [], json_encode(['message' => 'Forbidden.']))
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $client->setHttpClient($mockClient);

    expect(fn () => $client->get('applications'))
        ->toThrow(RuntimeException::class, 'Forbidden.');
});

it('shows a friendly message for connection failures', function () {
    $mockClient = createMockClient([
        new ConnectException(
            'Could not resolve host',
            new Request('GET', 'applications')
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $client->setHttpClient($mockClient);

    expect(fn () => $client->get('applications'))
        ->toThrow(RuntimeException::class, 'Unable to connect to the SecretStash API. Please check your network connection and API URL configuration.');
});

it('preserves the original exception in the chain', function () {
    $mockClient = createMockClient([
        new RequestException(
            'Not Found',
            new Request('GET', 'applications'),
            new Response(404, [], json_encode(['message' => 'Not Found.']))
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');
    $client->setHttpClient($mockClient);

    try {
        $client->get('applications');
    } catch (RuntimeException $e) {
        expect($e->getPrevious())->toBeInstanceOf(RequestException::class);

        return;
    }

    test()->fail('Expected RuntimeException was not thrown.');
});
