<?php

use Dniccum\SecretStash\Exceptions\ApiToken\InvalidApiToken;
use Dniccum\SecretStash\SecretStashClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('returns the API message from a JSON error response', function () {
    Http::fake([
        '*/api/applications' => Http::response(
            ['message' => 'The "local" environment does not exist for this application.'],
            404
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');

    expect(fn () => $client->get('applications'))
        ->toThrow(RuntimeException::class, 'The "local" environment does not exist for this application.');
});

it('falls back to status code when JSON has no message key', function () {
    Http::fake([
        '*/api/applications' => Http::response(
            ['error' => 'something went wrong'],
            422
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');

    expect(fn () => $client->get('applications'))
        ->toThrow(RuntimeException::class, 'API request failed with status code 422.');
});

it('falls back to status code when response body is not JSON', function () {
    Http::fake([
        '*/api/applications' => Http::response('Internal Server Error', 500),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');

    expect(fn () => $client->get('applications'))
        ->toThrow(RuntimeException::class, 'API request failed with status code 500.');
});

it('throws InvalidApiToken for 401 responses', function () {
    Http::fake([
        '*/api/applications' => Http::response(
            ['message' => 'Unauthenticated.'],
            401
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');

    expect(fn () => $client->get('applications'))
        ->toThrow(InvalidApiToken::class);
});

it('throws RuntimeException with API message for 403 responses', function () {
    Http::fake([
        '*/api/applications' => Http::response(
            ['message' => 'Forbidden.'],
            403
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');

    expect(fn () => $client->get('applications'))
        ->toThrow(RuntimeException::class, 'Forbidden.');
});

it('shows a friendly message for connection failures', function () {
    Http::fake(function () {
        throw new ConnectionException('Could not resolve host');
    });

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');

    expect(fn () => $client->get('applications'))
        ->toThrow(RuntimeException::class, 'Unable to connect to the SecretStash API. Please check your network connection and API URL configuration.');
});

it('preserves the original exception in the chain', function () {
    Http::fake([
        '*/api/applications' => Http::response(
            ['message' => 'Not Found.'],
            404
        ),
    ]);

    $client = new SecretStashClient('https://secret-stash.app', 'test-token');

    try {
        $client->get('applications');
    } catch (RuntimeException $e) {
        expect($e->getPrevious())->toBeInstanceOf(RequestException::class);

        return;
    }

    test()->fail('Expected RuntimeException was not thrown.');
});
