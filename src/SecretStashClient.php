<?php

namespace Dniccum\SecretStash;

use Dniccum\SecretStash\Enums\AgentType;
use Dniccum\SecretStash\Exceptions\ApiToken\InvalidApiToken;
use Dniccum\SecretStash\Exceptions\ApiToken\MissingApiToken;
use Dniccum\SecretStash\Exceptions\InvalidEnvironmentConfiguration;
use Dniccum\SecretStash\Support\ConfigResolver;
use Dniccum\SecretStash\Support\VariableUtility;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

class SecretStashClient
{
    protected string $apiUrl;

    protected ?string $apiToken;

    protected ?string $encryptionKey = null;

    protected string $apiVersion;

    protected ?Client $httpClient = null;

    /**
     * @throws InvalidEnvironmentConfiguration
     * @throws \Throwable
     */
    public function __construct(?string $apiUrl = null, ?string $apiToken = null, ?string $encryptionKey = null, ?string $apiVersion = null)
    {
        $this->apiUrl = $apiUrl ? rtrim($apiUrl, '/') : (ConfigResolver::get('api_url') ?? '');
        $this->apiToken = $apiToken ?? ConfigResolver::get('api_token');
        $this->encryptionKey = $encryptionKey;
        $this->apiVersion = trim($apiVersion ?? ConfigResolver::get('api_version') ?? 'v1', '/');

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
            $basePath = $this->apiVersion !== '' ? "/api/{$this->apiVersion}/" : '/api/';

            $this->httpClient = new Client([
                'base_uri' => rtrim($this->apiUrl, '/').$basePath,
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
        try {
            $response = $this->buildClient()->get($endpoint, [
                'query' => $query,
            ]);

            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Make a POST request to the API.
     */
    public function post(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->buildClient()->post($endpoint, [
                'json' => $data,
            ]);

            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Make a PUT request to the API.
     */
    public function put(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->buildClient()->put($endpoint, [
                'json' => $data,
            ]);

            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Make a DELETE request to the API.
     */
    public function delete(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->buildClient()->delete($endpoint, [
                'query' => $query,
            ]);

            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Handle API exceptions.
     */
    protected function handleException(\Throwable $e): never
    {
        if ($e instanceof RequestException && $e->hasResponse()) {
            $statusCode = $e->getResponse()->getStatusCode();

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

        if ($e instanceof RequestException && $e->hasResponse()) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();
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

    /**
     * Get all Agent Vault agents for the current user.
     */
    public function getAgents(): array
    {
        return $this->get('agents');
    }

    /**
     * Get a single Agent Vault agent.
     */
    public function getAgent(string $agentId): array
    {
        return $this->get("agents/{$agentId}");
    }

    /**
     * Create a new Agent Vault agent. The response includes the agent's
     * one-time plaintext API token, which is never returned again.
     */
    public function createAgent(AgentType|string $type, string $name, ?string $description = null): array
    {
        $data = [
            'name' => $name,
            'type' => $type instanceof AgentType ? $type->value : $type,
        ];

        if ($description !== null) {
            $data['description'] = $description;
        }

        return $this->post('agents', $data);
    }

    /**
     * Update an existing Agent Vault agent.
     */
    public function updateAgent(string $agentId, array $attributes): array
    {
        return $this->put("agents/{$agentId}", $attributes);
    }

    /**
     * Delete (revoke) an Agent Vault agent.
     */
    public function deleteAgent(string $agentId): array
    {
        return $this->delete("agents/{$agentId}");
    }

    /**
     * Sync the set of environments an agent is authorized to access.
     *
     * @param  array<int, string>  $environmentIds
     */
    public function syncAgentEnvironments(string $agentId, array $environmentIds): array
    {
        return $this->put("agents/{$agentId}/environments", [
            'environments' => $environmentIds,
        ]);
    }

    /**
     * Provision (seal and store) a data encryption key for an agent/environment pair.
     */
    public function provisionAgentEnvironmentDek(string $agentId, string $environmentId, string $sealedDek): array
    {
        return $this->post("agents/{$agentId}/environments/{$environmentId}/dek", [
            'sealed_dek' => $sealedDek,
        ]);
    }

    /**
     * Get metadata for all secrets an agent is authorized to resolve.
     */
    public function getAgentSecrets(string $agentId, ?string $environmentId = null): array
    {
        $query = $environmentId !== null ? ['environment' => $environmentId] : [];

        return $this->get("agents/{$agentId}/secrets", $query);
    }

    /**
     * Get metadata for a single secret an agent is authorized to resolve.
     */
    public function getAgentSecret(string $agentId, string $secretId): array
    {
        return $this->get("agents/{$agentId}/secrets/{$secretId}");
    }

    /**
     * Batch resolve decrypted secret values for an agent, scoped to its
     * authorized environments.
     *
     * @param  array<int, string>  $variables
     */
    public function resolveAgentSecrets(string $agentId, array $variables, ?string $environmentId = null): array
    {
        $data = ['variables' => $variables];

        if ($environmentId !== null) {
            $data['environment'] = $environmentId;
        }

        return $this->post("agents/{$agentId}/secrets/resolve", $data);
    }

    /**
     * Run the connection verification protocol for an agent (token validity,
     * DEK unseal/decrypt, and a live secret resolve round-trip).
     */
    public function testAgent(string $agentId): array
    {
        return $this->post("agents/{$agentId}/test");
    }
}
