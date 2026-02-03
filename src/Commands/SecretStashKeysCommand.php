<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\Exceptions\Keys\PrivateKeyFailedToSave;
use Dniccum\SecretStash\Exceptions\Keys\PrivateKeyNotFound;
use Dniccum\SecretStash\SecretStashClient;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class SecretStashKeysCommand extends BasicCommand
{
    /**
     * The minimum number of characters required for a password.
     *
     * @note While this is a security measure, this could be a feature to be controlled via the API.
     */
    protected int $passwordLength = 8;

    protected $signature = 'secret-stash:keys
                            {action? : The action to perform (status, init, sync)}
                            {--force : Force key regeneration even if server keys exist}';

    protected $description = 'Manage your user encryption keys (RSA key pair) for the CLI';

    protected string $keysDir;

    protected string $privateKeyFile;

    public function __construct()
    {
        parent::__construct();
        $this->keysDir = $this->defaultPrivateKeyDirectory();
        $this->privateKeyFile = $this->defaultPrivateKeyPath();

        if (! is_dir($this->keysDir)) {
            mkdir($this->keysDir, 0700, true);
        }
    }

    public function handle(SecretStashClient $client): int
    {
        $action = $this->argument('action') ?? select(
            'What would you like to do?',
            ['status', 'init', 'sync']
        );

        try {
            match ($action) {
                'status' => $this->showStatus($client),
                'init' => $this->initializeKeys($client),
                'sync' => $this->syncFromServer($client),
                default => $this->invalidAction($action),
            };

            return self::SUCCESS;
        } catch (\Exception $e) {
            error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function showStatus(SecretStashClient $client): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>Key Status</>');
        $this->newLine();

        // Check local keys
        $localKey = $this->loadLocalPrivateKey();
        if ($localKey) {
            $this->line('<fg=green>✓</> Local private key: <fg=green>Present</>');
        } else {
            $this->line('<fg=red>✗</> Local private key: <fg=red>Missing</>');
        }

        // Check server keys
        try {
            $response = $client->getUserKeys();
            $serverKey = $response['data'] ?? null;

            if ($serverKey && $serverKey['public_key']) {
                $this->line('<fg=green>✓</> Server public key: <fg=green>Present</>');
                $this->line('<fg=green>✓</> Server private key (encrypted): <fg=green>Present</>');
            } else {
                $this->line('<fg=red>✗</> Server keys: <fg=red>Not uploaded</>');
            }
        } catch (\Exception $e) {
            $this->line('<fg=red>✗</> Server keys: <fg=red>Unable to check</>');
        }

        $this->newLine();

        if (! $localKey) {
            info('Run "secret-stash:keys init" to generate your encryption keys.');
        }

        return self::SUCCESS;
    }

    protected function initializeKeys(SecretStashClient $client): int
    {
        // Check if keys already exist locally
        if ($this->hasLocalPrivateKey()) {
            $overwrite = confirm(
                label: 'Keys already exist locally. Generate new keys? (This will require re-sharing all environments)',
                default: false
            );

            if (! $overwrite) {
                info('Initialization cancelled.');

                return self::SUCCESS;
            }
        }

        if (! $this->confirmServerKeyOverwriteIfNeeded($client)) {
            info('Initialization cancelled.');

            $confirmSync = confirm('Would you like to sync your existing keys from the server?');

            if ($confirmSync) {
                $this->syncFromServer($client);
            }

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Initializing User Keys</>');
        $this->newLine();

        info('You will need to create a password to protect your private key. Make it secure, but also make sure this is something that you can easily remember.');
        info('This password is NEVER sent to the server and cannot be recovered if lost.');
        $this->newLine();

        $password = password(
            label: 'Enter a strong password for your private key',
            placeholder: 'Min '.$this->passwordLength.' characters',
            required: true,
            validate: fn ($value) => strlen($value) < $this->passwordLength
                ? 'Password must be at least '.$this->passwordLength.' characters.'
                : null
        );

        $confirmPassword = password(
            label: 'Confirm password',
            required: true
        );

        if ($password !== $confirmPassword) {
            error('Passwords do not match.');

            return self::FAILURE;
        }

        // Generate RSA key pair
        $keyPair = spin(
            callback: fn () => CryptoHelper::generateRSAKeyPair(),
            message: 'Generating RSA-4096 key pair (this may take a moment)...'
        );

        $this->newLine();
        info('Keys generated successfully!');

        // Encrypt private key with password
        $privateKeyPayload = CryptoHelper::encryptPrivateKey($keyPair['private_key'], $password);

        // Save locally
        $this->saveLocalPrivateKey($privateKeyPayload);
        info('Private key saved locally (encrypted).');

        // Upload to server
        try {
            $client->storeUserKeys($keyPair['public_key'], $privateKeyPayload);
            info('Keys uploaded to server successfully!');
        } catch (\Exception $e) {
            error('Failed to upload keys to server: '.$e->getMessage());
            warning('Your keys are saved locally but not on the server. Try running "secret-stash:keys sync" later.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Initialization complete!');
        $this->newLine();
        info('You can now push variables and share environments with your team.');

        return self::SUCCESS;
    }

    protected function syncFromServer(SecretStashClient $client): int
    {
        $this->newLine();
        info('Fetching keys from server...');

        try {
            $response = $client->getUserKeys();
            $serverKey = $response['data'] ?? null;

            if (! $serverKey || ! $serverKey['public_key']) {
                error('No keys found on server. Run "secret-stash:keys init" first.');

                return self::FAILURE;
            }

            // Save the encrypted private key locally
            $this->saveLocalPrivateKey($serverKey['private_key_payload']);

            $this->newLine();
            $this->line('<fg=green;options=bold>✓</> Keys synced from server!');
            $this->newLine();
            info('Your encrypted private key has been downloaded.');
            info('You will need your password to decrypt it when using variables.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            error('Failed to sync keys: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function hasLocalPrivateKey(): bool
    {
        return file_exists($this->privateKeyFile);
    }

    protected function hasServerKeys(SecretStashClient $client): bool
    {
        try {
            $response = $client->getUserKeys();
            $serverKey = $response['data'] ?? null;

            return (bool) ($serverKey && $serverKey['public_key']);
        } catch (\Exception $e) {
            warning('Unable to check for server keys. Continuing may invalidate existing access.');

            return false;
        }
    }

    protected function confirmServerKeyOverwriteIfNeeded(SecretStashClient $client): bool
    {
        if (! $this->hasServerKeys($client)) {
            return true;
        }

        if ($this->option('force')) {
            return true;
        }

        return confirm(
            label: 'Keys already exist on the server. Replacing them will require re-sharing all environments. Continue?',
            default: false
        );
    }

    protected function loadLocalPrivateKey(): ?array
    {
        if (! file_exists($this->privateKeyFile)) {
            return null;
        }

        $content = file_get_contents($this->privateKeyFile);
        if ($content === false) {
            return null;
        }

        $key = json_decode($content, true);

        return is_array($key) ? $key : null;
    }

    protected function saveLocalPrivateKey(array $privateKeyPayload): void
    {
        $content = json_encode($privateKeyPayload, JSON_PRETTY_PRINT);
        if (file_put_contents($this->privateKeyFile, $content) === false) {
            throw new PrivateKeyFailedToSave;
        }
        chmod($this->privateKeyFile, 0600);
    }

    /**
     * Get the user's decrypted private key (prompts for password if needed).
     */
    public function getDecryptedPrivateKey(?string $password = null): string
    {
        $privateKeyPayload = $this->loadLocalPrivateKey();

        if (! $privateKeyPayload) {
            throw new PrivateKeyNotFound;
        }

        if ($password === null) {
            $password = password(
                label: 'Enter your private key password',
                required: true
            );
        }

        return CryptoHelper::decryptPrivateKey($privateKeyPayload, $password);
    }
}
