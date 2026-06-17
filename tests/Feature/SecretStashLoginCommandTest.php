<?php

use Dniccum\SecretStash\Commands\SecretStashLoginCommand;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('secret-stash.api_url', 'https://secret-stash.app');
    Config::set('secret-stash.api_token', null);
});

it('cancels login when an existing token is configured and user declines', function () {
    Config::set('secret-stash.api_token', 'existing-token-value');

    $this->artisan('secret-stash:login')
        ->expectsConfirmation('An API token is already configured. Do you want to generate a new one?', 'no')
        ->expectsOutputToContain('Login cancelled.')
        ->assertSuccessful();
});

it('registers the login command', function () {
    $this->artisan('secret-stash:login --help')
        ->expectsOutputToContain('Authenticate with SecretStash')
        ->assertSuccessful();
});

it('shows error when API is unreachable', function () {
    $mock = new MockHandler([
        new ConnectException(
            'Could not resolve host',
            new Request('POST', 'cli/auth/sessions')
        ),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $mockClient = new Client(['handler' => $handlerStack]);

    $command = new class($mockClient) extends SecretStashLoginCommand
    {
        private Client $mockClient;

        public function __construct(Client $mockClient)
        {
            parent::__construct();
            $this->mockClient = $mockClient;
        }

        protected function createAuthSession(): ?array
        {
            try {
                $response = $this->mockClient->post("{$this->apiUrl}/api/cli/auth/sessions", [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                ]);

                $body = json_decode($response->getBody()->getContents(), true);

                return $body['data'] ?? null;
            } catch (Throwable $e) {
                $this->error('Failed to initiate login session. Please check your network connection and API URL.');

                return null;
            }
        }
    };

    $this->app->make(Kernel::class)->registerCommand($command);

    $this->artisan('secret-stash:login', ['--no-browser' => true])
        ->expectsOutputToContain('Failed to initiate login session')
        ->assertFailed();
});
