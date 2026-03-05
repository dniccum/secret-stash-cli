<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\SecretStashClient;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class SecretStashEnvelopeCommand extends BasicCommand
{
    protected $signature = 'secret-stash:envelope
                            {action? : The action to perform (rewrap, repair, reset)}
                            {--application= : The unique application ID that identifies your application within SecretStash}
                            {--environment= : Environment slug (defaults to APP_ENV value in .env file if set, otherwise prompts user to select an environment)}
                            {--old-key-file= : Path to the old private key PEM}
                            {--old-device-key-id= : Device key ID associated with the old private key}';

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
        } catch (\Throwable $e) {
            error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function rewrapEnvelope(SecretStashClient $client): int
    {
        $oldDeviceKeyId = $this->resolveOldDeviceKeyId();

        info('Fetching environment envelope...');

        $response = $client->getEnvironmentEnvelope($this->applicationId, $this->environmentSlug, $oldDeviceKeyId);
        $envelope = $response['data']['envelope'] ?? null;

        if (! $envelope) {
            error('No envelope found for the old device key.');

            return self::FAILURE;
        }

        $oldPrivateKey = $this->loadOldPrivateKey();
        $dek = CryptoHelper::openEnvelope($envelope, $oldPrivateKey);

        $keysCommand = new SecretStashKeysCommand;
        $currentDeviceKeyId = $keysCommand->getDeviceKeyId();
        $publicKey = $keysCommand->getDevicePublicKey();

        $newEnvelope = CryptoHelper::createEnvelope($dek, $publicKey);
        $client->storeEnvironmentEnvelope(
            $this->applicationId,
            $this->environmentSlug,
            $currentDeviceKeyId,
            $newEnvelope
        );
        $this->printSuccess();

        return self::SUCCESS;
    }

    protected function repairEnvelope(SecretStashClient $client): int
    {
        try {
            return $this->rewrapEnvelope($client);
        } catch (\Throwable $e) {
            if (! $this->confirmResetAfterFailure()) {
                return self::FAILURE;
            }

            return $this->resetEnvelope($client);
        }
    }

    protected function resetEnvelope(SecretStashClient $client): int
    {
        $userKeysResponse = $client->getUserKeys();
        $deviceKeys = $userKeysResponse['data'] ?? [];

        if (empty($deviceKeys)) {
            throw new \RuntimeException('No device keys found. Run "secret-stash:keys init" first.');
        }

        $dek = CryptoHelper::generateKey();
        $envelopes = [];

        foreach ($deviceKeys as $deviceKey) {
            $envelopes[] = [
                'device_key_id' => $deviceKey['id'],
                'envelope' => CryptoHelper::createEnvelope($dek, $deviceKey['public_key']),
            ];
        }

        $client->storeBulkEnvironmentEnvelopes(
            $this->applicationId,
            $this->environmentSlug,
            $envelopes
        );

        $this->printResetSuccess();

        return self::SUCCESS;
    }

    protected function loadOldPrivateKey(): string
    {
        $path = $this->resolveOldPrivateKeyPath();
        $path = $this->expandHomePath($path);

        if (! file_exists($path)) {
            throw new \RuntimeException('Old private key file not found.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Failed to read the old private key file.');
        }

        return $content;
    }

    protected function resolveOldPrivateKeyPath(): string
    {
        $path = $this->option('old-key-file');
        if ($path) {
            return $path;
        }

        $defaultPath = $this->defaultPrivateKeyPath();

        return text(
            label: 'Path to the old private key PEM',
            placeholder: $defaultPath,
            default: $defaultPath,
            required: true
        );
    }

    protected function resolveOldDeviceKeyId(): int
    {
        $id = $this->option('old-device-key-id');
        if ($id) {
            return (int) $id;
        }

        $value = text(
            label: 'Old device key ID',
            placeholder: '123',
            required: true
        );

        return (int) $value;
    }

    protected function defaultPrivateKeyPath(): string
    {
        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/tmp';

        return $homeDir.'/.secret-stash/device_private_key.pem';
    }

    protected function expandHomePath(string $path): string
    {
        if (! str_starts_with($path, '~')) {
            return $path;
        }

        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/tmp';

        return $homeDir.substr($path, 1);
    }

    protected function confirmResetAfterFailure(): bool
    {
        return confirm(
            label: 'Unable to rewrap the envelope. Reset the environment key and continue?',
            default: false
        );
    }

    protected function printSuccess(): void
    {
        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Envelope rewrapped successfully!');
        $this->newLine();
    }

    protected function printResetSuccess(): void
    {
        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Environment key reset successfully!');
        $this->line('<fg=yellow>Next:</> Re-upload your variables to encrypt them with the new key.');
        $this->newLine();
    }
}
