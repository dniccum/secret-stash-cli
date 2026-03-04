<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Commands\Traits\UsesApplicationId;
use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\SecretStashClient;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class SecretStashShareCommand extends BasicCommand
{
    use UsesApplicationId;

    protected $signature = 'secret-stash:share
                            {--application= : Application ID}
                            {--environment= : Environment slug (defaults to APP_ENV value in .env file if set, otherwise prompts user to select an environment)}';

    protected $description = 'Share an environment with team members';

    public function handle(SecretStashClient $client, SecretStashKeysCommand $keysCommand): int
    {
        try {
            $this->setEnvironment();

            $environmentSlug = $this->environmentSlug;

            $this->newLine();
            $this->line('<fg=cyan;options=bold>Environment Sharing Status</>');
            $this->newLine();

            // Get all device keys and their envelope status
            $response = $client->getEnvironmentEnvelopes($this->applicationId, $environmentSlug);
            $deviceKeys = $response['data']['device_keys'] ?? [];

            if (empty($deviceKeys)) {
                error('No device keys found for this environment.');

                return self::FAILURE;
            }

            // Display current status
            $statusRows = [];
            $needsEnvelope = [];
            foreach ($deviceKeys as $deviceKey) {
                $status = $deviceKey['has_envelope'] ? '<fg=green>✓ Has Access</>' : '<fg=yellow>⚠ Needs Access</>';

                $statusRows[] = [
                    $deviceKey['name'],
                    $deviceKey['email'],
                    $deviceKey['label'],
                    $deviceKey['key_type'],
                    $status,
                ];

                if ($deviceKey['needs_envelope']) {
                    $needsEnvelope[] = $deviceKey;
                }
            }

            table(
                ['Name', 'Email', 'Device', 'Type', 'Access Status'],
                $statusRows
            );

            if (empty($needsEnvelope)) {
                $this->newLine();
                info('All device keys already have access to this environment!');

                return self::SUCCESS;
            }

            $this->newLine();
            info(count($needsEnvelope).' device key(s) need access to this environment.');

            $share = confirm(
                label: 'Grant access to these device keys?',
                default: true
            );

            if (! $share) {
                info('Sharing cancelled.');

                return self::SUCCESS;
            }

            $privateKey = $keysCommand->getPrivateKey();
            $deviceKeyId = $keysCommand->getDeviceKeyId();

            // Get user's envelope for this environment
            $envelopeResponse = $client->getEnvironmentEnvelope($this->applicationId, $environmentSlug, $deviceKeyId);
            $userEnvelope = $envelopeResponse['data']['envelope'] ?? null;

            if (! $userEnvelope) {
                error('You do not have access to this environment. Cannot share.');

                return self::FAILURE;
            }

            // Decrypt the DEK from envelope
            info('Decrypting environment key...');
            $dek = CryptoHelper::openEnvelope($userEnvelope, $privateKey);

            // Create envelopes for each user who needs access
            $envelopes = [];

            foreach ($needsEnvelope as $deviceKey) {
                try {
                    // Create envelope encrypted for this user's public key
                    $envelope = CryptoHelper::createEnvelope($dek, $deviceKey['public_key']);
                    $envelopes[] = [
                        'device_key_id' => $deviceKey['device_key_id'],
                        'envelope' => $envelope,
                    ];
                } catch (\Exception $e) {
                    error("Failed to create envelope for {$deviceKey['name']}: ".$e->getMessage());
                }
            }

            if (empty($envelopes)) {
                error('Failed to create any envelopes.');

                return self::FAILURE;
            }

            // Upload envelopes in bulk
            $result = spin(
                callback: fn () => $client->storeBulkEnvironmentEnvelopes($this->applicationId, $environmentSlug, $envelopes),
                message: 'Creating envelopes for device keys...'
            );

            $this->newLine();
            $this->line('<fg=green;options=bold>✓</> Sharing complete!');
            $this->line('<fg=yellow>Created:</> '.$result['data']['created']);
            $this->line('<fg=yellow>Updated:</> '.$result['data']['updated']);

            if (! empty($result['data']['errors'])) {
                $this->newLine();
                error('Some errors occurred:');
                foreach ($result['data']['errors'] as $error) {
                    $this->line('  - '.$error);
                }
            }

            $this->newLine();

            return self::SUCCESS;
        } catch (\Exception $e) {
            error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
