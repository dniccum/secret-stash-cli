<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\SecretStashClient;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class SecretStashShareCommand extends BasicCommand
{
    protected $signature = 'secret-stash:share
                            {--organization= : Organization ID}
                            {--application= : Application ID}
                            {--environment= : Environment ID}';

    protected $description = 'Share an environment with team members';

    public function handle(SecretStashClient $client, SecretStashKeysCommand $keysCommand): int
    {
        try {
            $this->setEnvironment();

            $environmentId = $this->getEnvironmentId($client);

            $this->newLine();
            $this->line('<fg=cyan;options=bold>Environment Sharing Status</>');
            $this->newLine();

            // Get all members and their envelope status
            $response = $client->getEnvironmentEnvelopes($environmentId);
            $members = $response['data']['members'] ?? [];

            if (empty($members)) {
                error('No organization members found.');

                return self::FAILURE;
            }

            // Display current status
            $statusRows = [];
            $needsEnvelope = [];
            foreach ($members as $member) {
                $status = $member['has_envelope'] ? '<fg=green>✓ Has Access</>' : '<fg=yellow>⚠ Needs Access</>';
                $keyStatus = $member['has_public_key'] ? '<fg=green>✓</>' : '<fg=red>✗</>';

                $statusRows[] = [
                    $member['name'],
                    $member['email'],
                    $keyStatus,
                    $status,
                ];

                if ($member['needs_envelope']) {
                    $needsEnvelope[] = $member;
                }
            }

            table(
                ['Name', 'Email', 'Has Key', 'Access Status'],
                $statusRows
            );

            if (empty($needsEnvelope)) {
                $this->newLine();
                info('All team members with keys already have access to this environment!');

                return self::SUCCESS;
            }

            $this->newLine();
            info(count($needsEnvelope).' team member(s) need access to this environment.');

            $share = confirm(
                label: 'Grant access to these members?',
                default: true
            );

            if (! $share) {
                info('Sharing cancelled.');

                return self::SUCCESS;
            }

            // Get user's password to decrypt private key
            $this->newLine();
            $userPassword = password(
                label: 'Enter your private key password to decrypt the environment key',
                required: true
            );

            // Decrypt user's private key
            $privateKey = $keysCommand->getDecryptedPrivateKey($userPassword);

            // Get user's envelope for this environment
            $envelopeResponse = $client->getEnvironmentEnvelope($environmentId);
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

            foreach ($needsEnvelope as $member) {
                try {
                    // Create envelope encrypted for this user's public key
                    $envelope = CryptoHelper::createEnvelope($dek, $member['public_key']);
                    $envelopes[] = [
                        'user_id' => $member['user_id'],
                        'envelope' => $envelope,
                    ];
                } catch (\Exception $e) {
                    error("Failed to create envelope for {$member['name']}: ".$e->getMessage());
                }
            }

            if (empty($envelopes)) {
                error('Failed to create any envelopes.');

                return self::FAILURE;
            }

            // Upload envelopes in bulk
            $result = spin(
                callback: fn () => $client->storeBulkEnvironmentEnvelopes($environmentId, $envelopes),
                message: 'Creating envelopes for team members...'
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

    protected function getApplicationId(): string
    {
        $applicationId = $this->option('application');

        if (empty($applicationId)) {
            $applicationId = config('secret-stash.application_id');
        }

        return $applicationId;
    }

    protected function getEnvironmentId(SecretStashClient $client): string
    {
        $environmentId = $this->environmentSlug ?? $this->option('environment');

        if (! $environmentId) {
            $response = $client->getEnvironments($this->applicationId);
            $environments = $response['data'] ?? [];

            if (empty($environments)) {
                throw new \RuntimeException('No environments found.');
            }

            $choices = [];
            foreach ($environments as $env) {
                $choices[$env['id']] = $env['name'].' ('.$env['type'].')';
            }

            $environmentId = select(
                label: 'Select an environment',
                options: $choices
            );
        }

        return $environmentId;
    }
}
