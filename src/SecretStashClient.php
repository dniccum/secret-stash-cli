<?php

namespace Dniccum\SecretStash;

use Dniccum\SecretStash\Exceptions\ApiToken\InvalidApiToken;
use Dniccum\SecretStash\Exceptions\ApiToken\MissingApiToken;
use Dniccum\SecretStash\Exceptions\InvalidEnvironmentConfiguration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SecretStashClient
{
    protected Client $client;

    protected string $apiUrl;

    protected ?string $apiToken;

    protected ?string $encryptionKey = null;

    /**
     * @throws InvalidEnvironmentConfiguration
     * @throws \Throwable
     */
    public function __construct(?string $apiUrl = null, ?string $apiToken = null, ?string $encryptionKey = null)
    {
        $this->apiUrl = $apiUrl ? rtrim($apiUrl, '/') : config('secret-stash.api_url');
        $this->apiToken = $apiToken ?? config('secret-stash.api_token');
        $this->encryptionKey = $encryptionKey;

        if (empty($this->apiUrl)) {
            throw new InvalidEnvironmentConfiguration('API url is not configured. Please set SECRET_STASH_API_URL in your .env file.');
        }

        if (empty($this->apiToken)) {
            throw new InvalidEnvironmentConfiguration('API token is not configured. Please set SECRET_STASH_API_TOKEN in your .env file.');
        }

        $this->client = new Client([
            'base_uri' => $this->apiUrl.'/api/',
            'headers' => array_merge($this->getAuthHeaders(), [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]),
            'timeout' => 30,
        ]);
    }

    /**
     * Make a GET request to the API.
     */
    public function get(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->client->get($endpoint, [
                'query' => $query,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            $this->handleException($e);
        }
    }

    /**
     * Make a POST request to the API.
     */
    public function post(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->client->post($endpoint, [
                'json' => $data,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get authentication headers.
     */
    protected function getAuthHeaders(): array
    {
        if (! $this->apiToken) {
            throw new MissingApiToken;
        }

        return [
            'Authorization' => 'Bearer '.$this->apiToken,
        ];
    }

    /**
     * Handle API exceptions.
     */
    protected function handleException(\Throwable $e): never
    {
        if ($e->getCode() === 401 || $e->getCode() === 403) {
            throw new InvalidApiToken(
                code: $e->getCode(),
                previous: $e,
            );
        }
        throw new \RuntimeException('API request failed: '.$e->getMessage(), $e->getCode(), $e);
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
        $this->syncEnvFromVariables($variables, $envPath, $encryptionKey);
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
    public function storeDeviceKey(string $label, string $publicKey, string $keyType = 'device', array $metadata = []): array
    {
        return $this->post('user/keys', [
            'label' => $label,
            'key_type' => $keyType,
            'public_key' => $publicKey,
            'metadata' => $metadata ?: null,
        ]);
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
}
