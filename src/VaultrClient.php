<?php

namespace Dniccum\Vaultr;

use Dniccum\Vaultr\Exceptions\InvalidEnvironmentConfiguration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class VaultrClient
{
    protected Client $client;

    protected string $apiUrl;

    protected ?string $apiToken;

    /**
     * @throws \Throwable
     */
    public function __construct(?string $apiUrl = null, ?string $apiToken = null)
    {
        $this->apiUrl = $apiUrl ? rtrim($apiUrl, '/') : config('vaultr.api_url');
        $this->apiToken = $apiToken ?? config('vaultr.api_token');

        throw_if(empty($this->apiUrl), new InvalidEnvironmentConfiguration('API url is not configured. Please set VAULTR_API_URL in your .env file.'));
        throw_if(empty($this->apiToken), new InvalidEnvironmentConfiguration('API token is not configured. Please set VAULTR_API_TOKEN in your .env file.'));

        $this->client = new Client([
            'base_uri' => $this->apiUrl.'/api/',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
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
                'headers' => $this->getAuthHeaders(),
                'query' => $query,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('API request failed: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Make a POST request to the API.
     */
    public function post(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->client->post($endpoint, [
                'headers' => $this->getAuthHeaders(),
                'json' => $data,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('API request failed: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Make a PATCH request to the API.
     */
    public function patch(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->client->patch($endpoint, [
                'headers' => $this->getAuthHeaders(),
                'json' => $data,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('API request failed: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Make a DELETE request to the API.
     */
    public function delete(string $endpoint): array
    {
        try {
            $response = $this->client->delete($endpoint, [
                'headers' => $this->getAuthHeaders(),
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('API request failed: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get authentication headers.
     */
    protected function getAuthHeaders(): array
    {
        if (! $this->apiToken) {
            throw new \RuntimeException('API token is not configured. Please set VAULTR_API_TOKEN in your .env file.');
        }

        return [
            'Authorization' => 'Bearer '.$this->apiToken,
        ];
    }

    /**
     * Get all organizations.
     */
    public function getOrganizations(): array
    {
        return $this->get('organizations');
    }

    /**
     * Get a specific organization.
     */
    public function getOrganization(string $organizationId): array
    {
        return $this->get("organizations/{$organizationId}");
    }

    /**
     * Create a new organization.
     */
    public function createOrganization(string $name): array
    {
        return $this->post('organizations', ['name' => $name]);
    }

    /**
     * Get applications for an organization.
     */
    public function getApplications(string $organizationId): array
    {
        return $this->get("organizations/{$organizationId}/applications");
    }

    /**
     * Get a specific application.
     */
    public function getApplication(string $organizationId, string $applicationId): array
    {
        return $this->get("organizations/{$organizationId}/applications/{$applicationId}");
    }

    /**
     * Create a new application.
     */
    public function createApplication(string $organizationId, string $name): array
    {
        return $this->post("organizations/{$organizationId}/applications", ['name' => $name]);
    }

    /**
     * Get environments for an application.
     */
    public function getEnvironments(string $applicationId): array
    {
        return $this->get("/applications/{$applicationId}/environments");
    }

    /**
     * Create a new environment.
     */
    public function createEnvironment(string $applicationId, string $name, string $slug, string $type): array
    {
        return $this->post("/applications/{$applicationId}/environments", [
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
        return $this->get("/applications/{$applicationId}/environments/{$environmentSlug}");
    }

    /**
     * Sync variables into the project's .env file.
     *
     * This method accepts a variables payload (ideally from getVariables) and will:
     * - Update values for keys that already exist in the current .env file.
     * - Append missing keys with their corresponding values at the end of the file.
     *
     * Supported variable payload shapes (flexible):
     * - Associative map: [ 'APP_NAME' => 'My App', 'APP_ENV' => 'local' ]
     * - List of items with name/value: [ ['name' => 'APP_NAME', 'value' => 'My App'] ]
     * - List of items with name/payload: [ ['name' => 'APP_KEY', 'payload' => ['value' => 'secret', 'decrypted' => 'secret']] ]
     * - API structures wrapped in a top-level 'data' key: [ 'data' => [ ...items... ] ]
     *
     * You may optionally provide a custom .env path; by default the method tries to
     * resolve the Laravel project's base .env, falling back to the current working directory.
     */
    public function syncEnvFromVariables(array $variables, ?string $envPath = null): void
    {
        $envPath = $this->resolveEnvPath($envPath);
        $kv = $this->normalizeVariables($variables);

        // Ensure file exists; if not, create an empty one.
        if (! file_exists($envPath)) {
            @touch($envPath);
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException("Unable to read .env file at {$envPath}");
        }

        // Build a quick index of existing keys to their line positions (first occurrence).
        $keyLineIndex = [];
        foreach ($lines as $i => $line) {
            // Skip comments and blank lines
            if ($line === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            // Match KEY=VALUE where KEY does not contain spaces and starts the line
            if (preg_match('/^([A-Z0-9_]+)\s*=.*/', $line, $m)) {
                $key = $m[1];
                if (! isset($keyLineIndex[$key])) {
                    $keyLineIndex[$key] = $i;
                }
            }
        }

        $updatedKeys = [];
        foreach ($kv as $key => $value) {
            $formatted = $this->formatEnvAssignment($key, $value);
            if (isset($keyLineIndex[$key])) {
                $lines[$keyLineIndex[$key]] = $formatted;
            } else {
                $lines[] = $formatted;
            }
            $updatedKeys[] = $key;
        }

        // Optionally add a marker comment when appending new keys; kept simple to avoid noisy diffs.
        // If there is at least one new key and last line isn't blank, add a separator newline.
        if (! empty($updatedKeys) && ! empty($lines) && end($lines) !== '') {
            $lines[] = '';
        }

        $result = file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL, LOCK_EX);
        if ($result === false) {
            throw new \RuntimeException("Unable to write updated .env file at {$envPath}");
        }
    }

    /**
     * Convenience method: fetch variables via API and immediately sync to .env.
     */
    public function syncEnvFileFromApi(string $organizationId, string $applicationId, string $environmentId, ?string $envPath = null): void
    {
        $variables = $this->getVariables($organizationId, $applicationId, $environmentId);
        $this->syncEnvFromVariables($variables, $envPath);
    }

    /**
     * Resolve the .env file path. Prefer Laravel's base_path if available.
     */
    protected function resolveEnvPath(?string $envPath): string
    {
        if ($envPath) {
            return $envPath;
        }

        if (function_exists('base_path')) {
            return rtrim(base_path('.env'), DIRECTORY_SEPARATOR);
        }

        $cwd = getcwd() ?: __DIR__;

        return rtrim($cwd, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.env';
    }

    /**
     * Normalize a variety of input variable payloads into a simple [key => value] array.
     *
     * @param  array  $variables  Various shapes; see syncEnvFromVariables() docs.
     * @return array<string,string>
     */
    protected function normalizeVariables(array $variables): array
    {
        // If wrapped in a 'data' envelope, unwrap it.
        if (array_key_exists('data', $variables) && is_array($variables['data'])) {
            $variables = $variables['data'];
        }

        // Associative map case: all keys are strings and values are scalars
        $isAssoc = static function (array $arr): bool {
            foreach (array_keys($arr) as $k) {
                if (! is_int($k)) {
                    return true;
                }
            }

            return false;
        };

        $kv = [];
        if ($isAssoc($variables)) {
            // Filter out non-scalar values; cast to string
            foreach ($variables as $k => $v) {
                if (is_string($k)) {
                    if (is_scalar($v) || $v === null) {
                        $kv[$k] = $v === null ? '' : (string) $v;
                    }
                }
            }

            return $kv;
        }

        // List of items case
        foreach ($variables as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = $item['name'] ?? $item['key'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            $value = null;
            // Direct value
            if (array_key_exists('value', $item) && (is_scalar($item['value']) || $item['value'] === null)) {
                $value = $item['value'];
            }
            // Common payload structures
            if ($value === null && isset($item['payload']) && is_array($item['payload'])) {
                $payload = $item['payload'];
                if (array_key_exists('value', $payload) && (is_scalar($payload['value']) || $payload['value'] === null)) {
                    $value = $payload['value'];
                } elseif (array_key_exists('decrypted', $payload) && (is_scalar($payload['decrypted']) || $payload['decrypted'] === null)) {
                    $value = $payload['decrypted'];
                } elseif (array_key_exists('plain', $payload) && (is_scalar($payload['plain']) || $payload['plain'] === null)) {
                    $value = $payload['plain'];
                }
            }

            // Fallback: stringify a scalar-looking 'data'
            if ($value === null && isset($item['data']) && (is_scalar($item['data']) || $item['data'] === null)) {
                $value = $item['data'];
            }

            if ($value === null) {
                // Skip if value cannot be determined
                continue;
            }

            $kv[$name] = $value === null ? '' : (string) $value;
        }

        return $kv;
    }

    /**
     * Format a single KEY=VALUE assignment for .env files, with appropriate quoting.
     */
    protected function formatEnvAssignment(string $key, string $value): string
    {
        // Determine if we need quoting: empty, contains any whitespace, or special characters
        $needsQuotes = $value === ''
            || preg_match('/\s/', $value) === 1
            || strpbrk($value, '#"=\\') !== false;

        if ($needsQuotes) {
            // Escape existing double quotes and backslashes
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

            return $key.'="'.$escaped.'"';
        }

        return $key.'='.$value;
    }

    /**
     * Create a new variable.
     */
    public function createVariable(string $applicationId, string $environmentId, string $name, array $payload): array
    {
        return $this->post("/applications/{$applicationId}/environments/{$environmentId}/variables", [
            'name' => $name,
            'payload' => $payload,
        ]);
    }

    /**
     * Update a variable.
     */
    public function updateVariable(string $organizationId, string $applicationId, string $environmentId, string $variableId, string $name, ?array $payload = null): array
    {
        $data = ['name' => $name];
        if ($payload !== null) {
            $data['payload'] = $payload;
        }

        return $this->patch("organizations/{$organizationId}/applications/{$applicationId}/environments/{$environmentId}/variables/{$variableId}", $data);
    }

    /**
     * Delete a variable.
     */
    public function deleteVariable(string $organizationId, string $applicationId, string $environmentId, string $variableId): array
    {
        return $this->delete("organizations/{$organizationId}/applications/{$applicationId}/environments/{$environmentId}/variables/{$variableId}");
    }
}
