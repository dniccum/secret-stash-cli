<?php

namespace Dniccum\Vaultr\Commands;

use Dniccum\Vaultr\Crypto\CryptoHelper;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class VaultrKeysCommand extends BasicCommand
{
    protected $signature = 'vaultr:keys
                            {action? : The action to perform (list, set, generate)}
                            {--environment= : Environment ID}
                            {--key= : Base64url-encoded 32-byte key}';

    protected $description = 'Manage environment encryption keys for the CLI';

    protected string $keysFile;

    public function __construct()
    {
        parent::__construct();
        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/tmp';
        $vaultrDir = $homeDir.'/.vaultr';
        if (! is_dir($vaultrDir)) {
            mkdir($vaultrDir, 0700, true);
        }
        $this->keysFile = $vaultrDir.'/keys.json';
    }

    public function handle(): int
    {
        $action = $this->argument('action') ?? select(
            'What would you like to do?',
            ['list', 'set', 'generate']
        );

        return match ($action) {
            'list' => $this->listKeys(),
            'set' => $this->setKey(),
            'generate' => $this->generateKey(),
            default => $this->error("Invalid action: {$action}"),
        };
    }

    protected function listKeys(): int
    {
        $keys = $this->loadKeys();

        if (empty($keys)) {
            info('No environment keys configured.');

            return 0;
        }

        $rows = [];
        foreach ($keys as $envId => $key) {
            $rows[] = [
                $envId,
                substr($key, 0, 16).'...',
            ];
        }

        table(['Environment ID', 'Key (truncated)'], $rows);

        return 0;
    }

    protected function setKey(): int
    {
        $envId = $this->option('environment') ?? text(
            'Environment ID',
            required: true
        );

        $key = $this->option('key') ?? text(
            'Base64url-encoded key (32 bytes)',
            required: true
        );

        $keys = $this->loadKeys();
        $keys[$envId] = $key;
        $this->saveKeys($keys);

        info("Key set for environment: {$envId}");

        return 0;
    }

    protected function generateKey(): int
    {
        $envId = $this->option('environment') ?? text(
            'Environment ID (optional - will only display key if not provided)',
            required: false
        );

        $rawKey = CryptoHelper::generateKey();
        $encodedKey = CryptoHelper::base64urlEncode($rawKey);

        if ($envId) {
            $keys = $this->loadKeys();
            $keys[$envId] = $encodedKey;
            $this->saveKeys($keys);
            info("Generated and saved key for environment: {$envId}");
        }

        info("Generated key (base64url): {$encodedKey}");
        info('Save this key securely - you will need it to decrypt variables.');

        return 0;
    }

    protected function loadKeys(): array
    {
        if (! file_exists($this->keysFile)) {
            return [];
        }

        $content = file_get_contents($this->keysFile);
        if ($content === false) {
            error('Failed to read keys file.');

            return [];
        }

        $keys = json_decode($content, true);

        return is_array($keys) ? $keys : [];
    }

    protected function saveKeys(array $keys): void
    {
        $content = json_encode($keys, JSON_PRETTY_PRINT);
        if (file_put_contents($this->keysFile, $content) === false) {
            error('Failed to save keys file.');
        }
        chmod($this->keysFile, 0600);
    }
}
