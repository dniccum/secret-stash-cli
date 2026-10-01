<?php

namespace Dniccum\SecretStash;

use Dniccum\SecretStash\Exceptions\ApiToken\InvalidApiToken;
use Dniccum\SecretStash\Exceptions\ApiToken\MissingApiToken;
use Dniccum\SecretStash\Exceptions\InvalidEnvironmentConfiguration;
use Dniccum\SecretStash\Support\ConfigResolver;
use Dniccum\SecretStash\Support\VariableUtility;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class SecretStashClient
{
    /**
     * The versioned REST API prefix used by the Agent Vault endpoints.
     */
    public const API_VERSION = 'v1';

    protected string $apiUrl;

    protected ?string $apiToken;

    protected ?string $encryptionKey = null;

    protected ?Client $httpClient = null;

    /**
     * @throws InvalidEnvironmentConfiguration
     * @throws \Throwable
     */
    public function __construct(?string $apiUrl = null, ?string $apiToken = null, ?string $encryptionKey = null)
    {
        $this->apiUrl = $apiUrl ? rtrim($apiUrl, '/') : (ConfigResolver::get('api_url') ?? '');
        $this->apiToken = $apiToken ?? ConfigResolver::get('api_token');
        $this->encryptionKey = $encryptionKey;

        if (empty($this->apiUrl)) {
            throw new InvalidEnvironmentConfiguration('API url is not configured. Please set SECRET_STASH_API_URL in your .env file.');
        }

        if (empty($this->apiToken)) {
            throw new InvalidEnvironmentConfiguration('API token is not configured. Please set SECRET_STASH_API_TOKEN in your .env file.');
        }
    }

    /**
     * Build a configured Guzzle HTTP client instance.
     */
    protected function buildClient(): Client
    {
        if (! $this->apiToken) {
            throw new MissingApiToken;
        }

        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'base_uri' => rtrim($this->apiUrl, '/').'/api/',
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'User-Agent' => 'SecretStash-CLI/1.0',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30,
                'http_errors' => true,
            ]);
        }

        return $this->httpClient;
    }

    /**
     * Set a custom HTTP client (useful for testing).
     */
    public function setHttpClient(Client $client): static
    {
        $this->httpClient = $client;

        return $this;
    }

    /**
     * Make a GET request to the API.
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    /**
     * Make a POST request to the API.
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    /**
     * Make a PUT request to the API.
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, ['json' => $data]);
    }

    /**
     * Make a PATCH request to the API.
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, ['json' => $data]);
    }

    /**
     * Make a DELETE request to the API.
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Prefix an endpoint with the versioned API path (e.g. "v1/agents").
     */
    public function versioned(string $endpoint, string $version = self::API_VERSION): string
    {
        return trim($version, '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * Send a request and decode the JSON response.
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            $response = $this->buildClient()->request($method, $endpoint, $options);

            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get the response attached to an exception, if any.
     *
     * Guzzle 7 exposes getResponse() on RequestException (nullable), while Guzzle 8
     * moved it to ResponseException and removed hasResponse(). This supports both.
     */
    protected function getResponseFromException(\Throwable $e): ?ResponseInterface
    {
        if ($e instanceof RequestException && method_exists($e, 'getResponse')) {
            return $e->getResponse();
        }

        return null;
    }

    /**
     * Handle API exceptions.
     */
    protected function handleException(\Throwable $e): never
    {
        $response = $this->getResponseFromException($e);

        if ($response !== null) {
            $statusCode = $response->getStatusCode();

            if ($statusCode === 401) {
                throw new InvalidApiToken(
                    code: $statusCode,
                    previous: $e,
                );
            }
        }

        throw new \RuntimeException($this->formatErrorMessage($e), $e->getCode(), $e);
    }

    /**
     * Extract a clean, user-friendly error message from an exception.
     */
    protected function formatErrorMessage(\Throwable $e): string
    {
        if ($e instanceof ConnectException) {
            return 'Unable to connect to the SecretStash API. Please check your network connection and API URL configuration.';
        }

        $response = $this->getResponseFromException($e);

        if ($response !== null) {
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
                return $decoded['message'];
            }

            return "API request failed with status code {$statusCode}.";
        }

        return 'An unexpected API error occurred. Please try again.';
    }

    /**
     * Get all applications for the current organization.
     */
    public function getApplications(): array
    {
        return $this->get('applications', []);
    }

    /**
     * Get environments for an application.
     */
    public function getEnvironments(string $applicationId): array
    {
        return $this->get("applications/{$applicationId}/environments");
    }

    /**
     * Create a new environment.
     */
    public function createEnvironment(string $applicationId, string $name, string $slug, string $type): array
    {
        return $this->post("applications/{$applicationId}/environments", [
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
        ]);
    }

    /**
     * Get variables for an environment.
     */
    public function getVariables(string $applicationId, string $environmentSlug): array
    {
        return $this->get("applications/{$applicationId}/environments/{$environmentSlug}");
    }

    /**
     * Convenience method: fetch variables via API and immediately sync to .env.
     *
     * @note Keep this method for now for future compatibility with SecretStashKeysCommand.
     */
    public function syncEnvFileFromApi(string $applicationId, string $environmentId, ?string $envPath = null, ?string $encryptionKey = null): void
    {
        $variables = $this->getVariables($applicationId, $environmentId);
        $data = $variables['data'] ?? [];

        $envFile = $envPath ?? '.env';
        $content = file_exists($envFile) ? file_get_contents($envFile) : '';

        // Extract key-value pairs from ApplicationEnvironmentVariable objects
        $kvPairs = [];
        foreach ($data as $var) {
            $name = $var['name'] ?? null;
            $payload = $var['payload'] ?? [];
            if ($name && isset($payload['value'])) {
                // If it's already decrypted or we have the key to decrypt it
                // Note: SecretStashClient doesn't handle decryption itself currently,
                // but this method seems to expect raw variables to be synced.
                // Assuming payload['value'] is what we want to sync.
                $kvPairs[$name] = $payload['value'];
            }
        }

        $merged = VariableUtility::mergeEnvContent($content, $kvPairs);
        file_put_contents($envFile, $merged);
    }

    /**
     * Create a new variable.
     */
    public function createVariable(string $applicationId, string $environmentId, string $name, array $payload): array
    {
        return $this->post("applications/{$applicationId}/environments/{$environmentId}/variables", [
            'name' => $name,
            'payload' => $payload,
        ]);
    }

    /**
     * Get current user's device keys.
     */
    public function getUserKeys(): array
    {
        return $this->get('user/keys');
    }

    /**
     * Store/update current user's device key.
     */
    public function storeDeviceKey(string $label, string $publicKey, string $keyType = 'device', array $metadata = [], bool $isTemporary = false, ?int $ttlMinutes = null): array
    {
        $data = [
            'label' => $label,
            'key_type' => $keyType,
            'public_key' => $publicKey,
            'metadata' => $metadata ?: null,
        ];

        if ($isTemporary) {
            $data['is_temporary'] = true;
            $data['ttl_minutes'] = $ttlMinutes ?? 15;
        }

        return $this->post('user/keys', $data);
    }

    /**
     * Get current device envelope for an environment.
     */
    public function getEnvironmentEnvelope(string $applicationId, string $environmentSlug, int $deviceKeyId): array
    {
        return $this->get("applications/{$applicationId}/environments/{$environmentSlug}/envelope", [
            'device_key_id' => $deviceKeyId,
        ]);
    }

    /**
     * Store/update current device envelope for an environment.
     */
    public function storeEnvironmentEnvelope(string $applicationId, string $environmentSlug, int $deviceKeyId, array $envelope): array
    {
        return $this->post("applications/{$applicationId}/environments/{$environmentSlug}/envelope", [
            'device_key_id' => $deviceKeyId,
            'envelope' => $envelope,
        ]);
    }

    /**
     * Get all user envelopes for an environment (shows who has access).
     */
    public function getEnvironmentEnvelopes(string $applicationId, string $environmentSlug): array
    {
        return $this->get("applications/{$applicationId}/environments/{$environmentSlug}/envelopes");
    }

    /**
     * Bulk create/update envelopes for multiple device keys (for sharing).
     *
     * @param  array  $envelopes  Array of ['device_key_id' => int, 'envelope' => array]
     */
    public function storeBulkEnvironmentEnvelopes(string $applicationId, string $environmentSlug, array $envelopes): array
    {
        return $this->post("applications/{$applicationId}/environments/{$environmentSlug}/envelopes", [
            'envelopes' => $envelopes,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Agent Vault (v1)
    |--------------------------------------------------------------------------
    */

    /**
     * List agents.
     */
    public function getAgents(): array
    {
        return $this->get($this->versioned('agents'));
    }

    /**
     * Get a single agent.
     */
    public function getAgent(string $agentId): array
    {
        return $this->get($this->versioned("agents/{$agentId}"));
    }

    /**
     * Create an agent. The response contains the one-time API token.
     */
    public function createAgent(string $name, array $attributes = []): array
    {
        return $this->post($this->versioned('agents'), ['name' => $name] + $attributes);
    }

    /**
     * Update an agent.
     */
    public function updateAgent(string $agentId, array $attributes): array
    {
        return $this->put($this->versioned("agents/{$agentId}"), $attributes);
    }

    /**
     * Delete an agent.
     */
    public function deleteAgent(string $agentId): array
    {
        return $this->delete($this->versioned("agents/{$agentId}"));
    }

    /**
     * Get the environments an agent has access to.
     */
    public function getAgentEnvironments(string $agentId): array
    {
        return $this->get($this->versioned("agents/{$agentId}/environments"));
    }

    /**
     * Sync the environments an agent has access to.
     *
     * @param  array  $environmentIds  Environment IDs the agent should be scoped to
     */
    public function syncAgentEnvironments(string $agentId, array $environmentIds): array
    {
        return $this->put($this->versioned("agents/{$agentId}/environments"), [
            'environments' => array_values($environmentIds),
        ]);
    }

    /**
     * Provision the data encryption key (DEK) for an agent/environment pair.
     */
    public function provisionAgentDek(string $agentId, string $environmentId, array $data = []): array
    {
        return $this->post($this->versioned("agents/{$agentId}/environments/{$environmentId}/dek"), $data);
    }

    /**
     * List the secrets available to an agent.
     */
    public function getAgentSecrets(string $agentId, array $query = []): array
    {
        return $this->get($this->versioned("agents/{$agentId}/secrets"), $query);
    }

    /**
     * Get a single secret available to an agent.
     */
    public function getAgentSecret(string $agentId, string $secret, array $query = []): array
    {
        return $this->get($this->versioned("agents/{$agentId}/secrets/".rawurlencode($secret)), $query);
    }

    /**
     * Resolve a batch of secrets for an agent.
     *
     * @param  array  $secrets  Secret names/references to resolve
     */
    public function resolveAgentSecrets(string $agentId, array $secrets, array $options = []): array
    {
        return $this->post($this->versioned("agents/{$agentId}/secrets"), ['secrets' => array_values($secrets)] + $options);
    }

    /**
     * Verify that an agent's connection and credentials work.
     */
    public function testAgent(string $agentId): array
    {
        return $this->post($this->versioned("agents/{$agentId}/test"));
    }
}
