<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\SecretStashClient;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class SecretStashEnvelopeCommand extends BasicCommand
{
    protected $signature = 'secret-stash:envelope
                            {action? : The action to perform (rewrap, repair, reset)}
                            {--application= : Application ID}
                            {--environment= : Environment ID}
                            {--old-key-file= : Path to the old encrypted private key JSON}';

    protected $description = 'Rewrap environment key envelopes';

    public function handle(SecretStashClient $client): int
    {
        $action = $this->argument('action') ?? select(
            'What would you like to do?',
            ['rewrap', 'repair', 'reset']
        );

        try {
            $this->setEnvironment();

            return match ($action) {
                'rewrap' => $this->rewrapEnvelope($client),
                'repair' => $this->repairEnvelope($client),
                'reset' => $this->resetEnvelope($client),
                default => $this->invalidAction($action),
            };
        } catch (\Exception $e) {
            error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function rewrapEnvelope(SecretStashClient $client): int
    {
        $environmentId = $this->getEnvironmentId($client);

        info('Fetching environment envelope...');

        $response = $client->getEnvironmentEnvelope($environmentId);
        $envelope = $response['data']['envelope'] ?? null;

        if (! $envelope) {
            error('No envelope found for this environment.');

            return self::FAILURE;
        }

        $privateKeyPayload = $this->loadOldPrivateKeyPayload();
        $privateKeyPassword = $this->resolveOldPrivateKeyPassword();
        $oldPrivateKey = CryptoHelper::decryptPrivateKey($privateKeyPayload, $privateKeyPassword);
        $dek = CryptoHelper::openEnvelope($envelope, $oldPrivateKey);

        $userKeysResponse = $client->getUserKeys();
        $publicKey = $userKeysResponse['data']['public_key'] ?? null;

        if (! $publicKey) {
            throw new \RuntimeException('No user keys found. Run "secret-stash:keys init" first.');
        }

        $newEnvelope = CryptoHelper::createEnvelope($dek, $publicKey);
        $client->storeEnvironmentEnvelope($environmentId, $newEnvelope);
        $this->printSuccess();

        return self::SUCCESS;
    }

    protected function repairEnvelope(SecretStashClient $client): int
    {
        try {
            return $this->rewrapEnvelope($client);
        } catch (\Throwable $e) {
            $confirm = confirm(
                label: 'Unable to rewrap the envelope. Reset the environment key and continue?',
                default: false
            );
            if (! $confirm) {
                return self::FAILURE;
            }

            return $this->resetEnvelope($client);
        }
    }

    protected function resetEnvelope(SecretStashClient $client): int
    {
        $environmentId = $this->getEnvironmentId($client);

        $userKeysResponse = $client->getUserKeys();
        $publicKey = $userKeysResponse['data']['public_key'] ?? null;

        if (! $publicKey) {
            throw new \RuntimeException('No user keys found. Run "secret-stash:keys init" first.');
        }

        $dek = CryptoHelper::generateKey();
        $envelope = CryptoHelper::createEnvelope($dek, $publicKey);
        $client->storeEnvironmentEnvelope($environmentId, $envelope);

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Environment key reset successfully!');
        $this->line('<fg=yellow>Next:</> Re-upload your variables to encrypt them with the new key.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadOldPrivateKeyPayload(): array
    {
        $path = $this->resolveOldPrivateKeyPayloadPath();
        $path = $this->expandHomePath($path);

        if (! file_exists($path)) {
            throw new \RuntimeException('Old private key file not found.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Failed to read the old private key file.');
        }

        $payload = json_decode($content, true);
        if (! is_array($payload)) {
            throw new \RuntimeException('Old private key payload is invalid.');
        }

        return $payload;
    }

    protected function resolveOldPrivateKeyPayloadPath(): string
    {
        $path = $this->option('old-key-file');
        if ($path) {
            return $path;
        }

        $defaultPath = $this->defaultPrivateKeyPath();

        return text(
            label: 'Path to the old encrypted private key JSON',
            placeholder: $defaultPath,
            default: $defaultPath,
            required: true
        );
    }

    protected function resolveOldPrivateKeyPassword(): string
    {
        return password(
            label: 'Enter the old private key password',
            required: true
        );
    }

    protected function expandHomePath(string $path): string
    {
        if (! str_starts_with($path, '~')) {
            return $path;
        }

        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/tmp';

        return $homeDir.substr($path, 1);
    }

    protected function printSuccess(): void
    {
        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Envelope rewrapped successfully!');
        $this->newLine();
    }
}
