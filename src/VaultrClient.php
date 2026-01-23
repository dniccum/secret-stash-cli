<?php

namespace Dniccum\Vaultr;

use Dniccum\Vaultr\Crypto\CryptoHelper;
use Dniccum\Vaultr\Exceptions\ApiToken\InvalidApiToken;
use Dniccum\Vaultr\Exceptions\ApiToken\MissingApiToken;
use Dniccum\Vaultr\Exceptions\InvalidEnvironmentConfiguration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class VaultrClient
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
        $this->apiUrl = $apiUrl ? rtrim($apiUrl, '/') : config('vaultr.api_url');
        $this->apiToken = $apiToken ?? config('vaultr.api_token');
        $this->encryptionKey = $encryptionKey;

        if (empty($this->apiUrl)) {
            throw new InvalidEnvironmentConfiguration('API url is not configured. Please set VAULTR_API_URL in your .env file.');
        }

        if (empty($this->apiToken)) {
            throw new InvalidEnvironmentConfiguration('API token is not configured. Please set VAULTR_API_TOKEN in your .env file.');
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
        /** @phpstan-ignore-next-line */
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
        /** @phpstan-ignore-next-line */
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
     *
     * @param \Throwable $e
     * @return void
     */
    protected function handleException(\Throwable $e): void
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
    public function syncEnvFromVariables(array $variables, ?string $envPath = null, ?string $encryptionKey = null): void
    {
        if ($encryptionKey) {
            $this->encryptionKey = $encryptionKey;
        }

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
            if (preg_match('/^([-A-Z0-9_.]+)\s*=.*/i', $line, $m)) {
                $key = strtolower($m[1]);
                if (! isset($keyLineIndex[$key])) {
                    $keyLineIndex[$key] = $i;
                }
            }
        }

        $updatedKeys = [];
        $ignoredVariables = config('vaultr.ignored_variables', []);

        foreach ($kv as $key => $value) {
            if (in_array($key, $ignoredVariables, true)) {
                continue;
            }

            $formatted = $this->formatEnvAssignment($key, $value);
            $lookupKey = strtolower($key);
            if (isset($keyLineIndex[$lookupKey])) {
                $lines[$keyLineIndex[$lookupKey]] = $formatted;
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
     *
     * @note Keep this method for now for future compatibility with VaultrKeysCommand.
     */
    public function syncEnvFileFromApi(string $applicationId, string $environmentId, ?string $envPath = null, ?string $encryptionKey = null): void
    {
        $variables = $this->getVariables($applicationId, $environmentId);
        $this->syncEnvFromVariables($variables, $envPath, $encryptionKey);
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
                    if (is_scalar($v)) {
                        $kv[$k] = (string) $v;
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
            // Common payload structures
            if (isset($item['payload']) && is_array($item['payload'])) {
                $payload = $item['payload'];

                if ($this->encryptionKey && isset($payload['alg']) && $payload['alg'] === 'AES-GCM') {
                    try {
                        $rawKey = CryptoHelper::base64urlDecode($this->encryptionKey);
                        $value = CryptoHelper::aesGcmDecrypt($payload, $rawKey);
                    } catch (\Throwable $e) {
                        // If decryption fails, we'll try to fallback or skip
                    }
                }

                if ($value === null) {
                    if (array_key_exists('value', $payload) && (is_scalar($payload['value']) || $payload['value'] === null)) {
                        $value = $payload['value'];
                    } elseif (array_key_exists('decrypted', $payload) && (is_scalar($payload['decrypted']) || $payload['decrypted'] === null)) {
                        $value = $payload['decrypted'];
                    } elseif (array_key_exists('plain', $payload) && (is_scalar($payload['plain']) || $payload['plain'] === null)) {
                        $value = $payload['plain'];
                    } elseif (array_key_exists('ct', $payload) && (is_scalar($payload['ct']) || $payload['ct'] === null)) {
                        $value = $payload['ct'];
                    }
                }
            }

            // Direct value
            if ($value === null && array_key_exists('value', $item) && (is_scalar($item['value']) || $item['value'] === null)) {
                $value = $item['value'];
            }

            // Fallback: stringify a scalar-looking 'data'
            if ($value === null && array_key_exists('data', $item) && (is_scalar($item['data']) || $item['data'] === null)) {
                $value = $item['data'];
            }

            if ($value === null) {
                // Skip if value cannot be determined or is null
                continue;
            }

            $kv[$name] = (string) $value;
        }

        return $kv;
    }

    /**
     * Format a single KEY=VALUE assignment for .env files, with appropriate quoting.
     */
    protected function formatEnvAssignment(string $key, string $value): string
    {
        // If the value is already wrapped in double quotes, we strip them first
        // so we can re-evaluate if it needs quoting and avoid double-encoding.
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }

        // Determine if we need quoting: empty, contains any whitespace, special characters, or dynamic variable syntax ${
        $needsQuotes = $value === ''
            || preg_match('/\s/', $value) === 1
            || str_contains($value, '${')
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
        return $this->post("applications/{$applicationId}/environments/{$environmentId}/variables", [
            'name' => $name,
            'payload' => $payload,
        ]);
    }
}
