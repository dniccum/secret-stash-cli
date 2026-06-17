<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Support\ConfigResolver;
use Dniccum\SecretStash\Support\VariableUtility;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class SecretStashLoginCommand extends BasicCommand
{
    protected $signature = 'secret-stash:login
        {--no-browser : Do not attempt to open the browser automatically}';

    protected $description = 'Authenticate with SecretStash and store an API token locally';

    protected string $apiUrl;

    public function handle(): int
    {
        $this->apiUrl = rtrim(ConfigResolver::get('api_url') ?? 'https://secretstash.cloud', '/');

        $existingToken = ConfigResolver::get('api_token');
        if ($existingToken) {
            $overwrite = confirm('An API token is already configured. Do you want to generate a new one?', false);
            if (! $overwrite) {
                info('Login cancelled.');

                return self::SUCCESS;
            }
        }

        $session = $this->createAuthSession();
        if (! $session) {
            return self::FAILURE;
        }

        $verifyUrl = $session['verify_url'];
        info("Opening browser to authorize CLI access...\n");
        $this->line("  If your browser doesn't open, visit this URL:\n");
        $this->line("  <href={$verifyUrl}>{$verifyUrl}</>");
        $this->newLine();

        if (! $this->option('no-browser')) {
            $this->openBrowser($verifyUrl);
        }

        $token = spin(
            fn () => $this->pollForToken($session['session_code'], $session['expires_at']),
            'Waiting for authorization...'
        );

        if (! $token) {
            warning('Authorization timed out. Please try again.');

            return self::FAILURE;
        }

        $this->storeToken($token);
        info('Login successful! API token stored in .env file.');

        return self::SUCCESS;
    }

    protected function createAuthSession(): ?array
    {
        try {
            $client = new Client(['timeout' => 10]);
            $response = $client->post("{$this->apiUrl}/api/cli/auth/sessions", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return $body['data'] ?? null;
        } catch (RequestException $e) {
            $this->error('Failed to initiate login session. Please check your network connection and API URL.');

            return null;
        }
    }

    protected function pollForToken(string $sessionCode, string $expiresAt): ?string
    {
        $client = new Client(['timeout' => 10]);
        $deadline = strtotime($expiresAt);

        while (time() < $deadline) {
            sleep(2);

            try {
                $response = $client->get("{$this->apiUrl}/api/cli/auth/sessions/{$sessionCode}", [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ]);

                $body = json_decode($response->getBody()->getContents(), true);
                $data = $body['data'] ?? [];

                if (($data['status'] ?? '') === 'authorized' && ! empty($data['token'])) {
                    return $data['token'];
                }

                if (($data['status'] ?? '') === 'expired') {
                    return null;
                }
            } catch (\Throwable) {
                // continue polling
            }
        }

        return null;
    }

    protected function storeToken(string $token): void
    {
        $envPath = getcwd().'/.env';
        $content = file_exists($envPath) ? file_get_contents($envPath) : '';

        $merged = VariableUtility::mergeEnvContent($content, [
            'SECRET_STASH_API_TOKEN' => $token,
        ]);

        file_put_contents($envPath, $merged);
    }

    protected function openBrowser(string $url): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            @exec('start "" '.escapeshellarg($url));
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            @exec('open '.escapeshellarg($url).' > /dev/null 2>&1 &');
        } else {
            @exec('xdg-open '.escapeshellarg($url).' > /dev/null 2>&1 &');
        }
    }
}
