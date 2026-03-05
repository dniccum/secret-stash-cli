<?php

namespace Dniccum\SecretStash\Commands;

use Dniccum\SecretStash\Contracts\RSAKeyPair;
use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\Exceptions\Keys\DeviceKeyNotRegistered;
use Dniccum\SecretStash\Exceptions\Keys\MetaKeyFailedToSave;
use Dniccum\SecretStash\Exceptions\Keys\PrivateKeyFailedToSave;
use Dniccum\SecretStash\Exceptions\Keys\PrivateKeyNotFound;
use Dniccum\SecretStash\SecretStashClient;
use Endroid\QrCode\Matrix\MatrixInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class SecretStashKeysCommand extends BasicCommand
{
    protected $signature = 'secret-stash:keys
                            {action? : The action to perform (status, init, sync, recovery)}
                            {--force : Force device key regeneration}
                            {--label= : Device label for this machine}
                            {--copies=1 : Number of recovery share copies to print}
                            {--output-dir= : Directory to save recovery share files}';

    protected $description = 'Manage your SecretStash device keys';

    public function handle(SecretStashClient $client): int
    {
        $action = $this->argument('action') ?? select(
            'What would you like to do?',
            ['status', 'init', 'sync', 'recovery']
        );

        try {
            match ($action) {
                'status' => $this->showStatus($client),
                'init' => $this->initializeKeys($client),
                'sync' => $this->syncFromServer($client),
                'recovery' => $this->generateRecoveryKey($client),
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
        $this->line('<fg=cyan;options=bold>Device Key Status</>');
        $this->newLine();

        $localPrivateKey = $this->loadPrivateKey();
        $localMeta = $this->loadDeviceMetadata();

        if ($localPrivateKey) {
            $this->line('<fg=green>✓</> Local private key: <fg=green>Present</>');
        } else {
            $this->line('<fg=red>✗</> Local private key: <fg=red>Missing</>');
        }

        if ($localMeta) {
            $deviceId = $localMeta['device_key_id'] ?? 'Unknown';
            $label = $localMeta['label'] ?? 'Unknown';
            $this->line("<fg=green>✓</> Device record: <fg=green>Present</> (ID: {$deviceId}, {$label})");
        } else {
            $this->line('<fg=red>✗</> Device record: <fg=red>Missing</>');
        }

        try {
            $response = $client->getUserKeys();
            $serverKeys = $response['data'] ?? [];
            $this->line('<fg=green>✓</> Server device keys: <fg=green>'.count($serverKeys).'</>');

            if ($localMeta && ($localMeta['fingerprint'] ?? null)) {
                $match = collect($serverKeys)->firstWhere('fingerprint', $localMeta['fingerprint']);
                if ($match) {
                    $this->line('<fg=green>✓</> This device is registered on the server.');
                } else {
                    $this->line('<fg=yellow>⚠</> This device is not registered on the server.');
                }
            }
        } catch (\Exception $e) {
            $this->line('<fg=red>✗</> Server keys: <fg=red>Unable to check</>');
        }

        $this->newLine();

        if (! $localPrivateKey || ! $localMeta) {
            info('Run "secret-stash:keys init" to generate and register this device.');
        }

        return self::SUCCESS;
    }

    protected function initializeKeys(SecretStashClient $client): int
    {
        if ($this->hasLocalPrivateKey() && ! $this->option('force')) {
            $overwrite = confirm(
                label: 'Device keys already exist locally. Generate new keys? (This device will need access re-granted)',
                default: false
            );

            if (! $overwrite) {
                info('Initialization cancelled.');

                return self::SUCCESS;
            }
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Initializing Device Keys</>');
        $this->newLine();

        $label = $this->resolveDeviceLabel();

        /**
         * @var RSAKeyPair $keyPair
         */
        $keyPair = spin(
            callback: fn () => CryptoHelper::generateRSAKeyPair(),
            message: 'Generating RSA-4096 key pair (this may take a moment)...'
        );

        $this->savePrivateKey($keyPair->private_key);
        info('Private key saved locally (device-bound).');

        $metadata = [
            'label' => $label,
            'hostname' => gethostname() ?: null,
            'platform' => PHP_OS_FAMILY,
        ];

        $response = $client->storeDeviceKey($label, $keyPair['public_key'], 'device', $metadata);
        $deviceKey = $response['data'] ?? null;

        if (! $deviceKey || ! isset($deviceKey['id'])) {
            throw new PrivateKeyFailedToSave('Failed to register device key.');
        }

        $this->saveDeviceMetadata([
            'device_key_id' => $deviceKey['id'],
            'label' => $deviceKey['label'] ?? $label,
            'public_key' => $deviceKey['public_key'] ?? $keyPair['public_key'],
            'fingerprint' => $deviceKey['fingerprint'] ?? CryptoHelper::fingerprint($keyPair['public_key']),
        ]);

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Device key registered!');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function syncFromServer(SecretStashClient $client): int
    {
        $this->newLine();
        info('Syncing device registration from server...');

        $localMeta = $this->loadDeviceMetadata();
        if (! $localMeta) {
            error('No local device record found. Run "secret-stash:keys init" first.');

            return self::FAILURE;
        }

        $fingerprint = $localMeta['fingerprint'] ?? null;
        if (! $fingerprint) {
            error('Local device record missing fingerprint.');

            return self::FAILURE;
        }

        $response = $client->getUserKeys();
        $serverKeys = $response['data'] ?? [];
        $match = collect($serverKeys)->firstWhere('fingerprint', $fingerprint);

        if (! $match) {
            error('No matching device key found on the server. Run "secret-stash:keys init" to register.');

            return self::FAILURE;
        }

        $this->saveDeviceMetadata([
            'device_key_id' => $match['id'],
            'label' => $match['label'] ?? $localMeta['label'] ?? 'Device',
            'public_key' => $match['public_key'] ?? $localMeta['public_key'] ?? null,
            'fingerprint' => $match['fingerprint'] ?? $fingerprint,
        ]);

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Device registration synced.');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function generateRecoveryKey(SecretStashClient $client): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>Generating Recovery Key</>');
        $this->newLine();

        $response = $client->getUserKeys();
        $existingRecovery = collect($response['data'] ?? [])->firstWhere('key_type', 'recovery');

        if ($existingRecovery && ! $this->option('force')) {
            $replace = confirm(
                label: 'A recovery key already exists. Replace it?',
                default: false
            );

            if (! $replace) {
                info('Recovery key generation cancelled.');

                return self::SUCCESS;
            }
        }

        $keyPair = spin(
            callback: fn () => CryptoHelper::generateRSAKeyPair(),
            message: 'Generating RSA-4096 recovery key...'
        );

        $fingerprint = CryptoHelper::fingerprint($keyPair['public_key']);
        $share = CryptoHelper::encodeRecoveryShare($keyPair['private_key'], $fingerprint);

        $response = $client->storeDeviceKey('Recovery Key', $keyPair['public_key'], 'recovery');
        $deviceKey = $response['data'] ?? null;

        if (! $deviceKey || ! isset($deviceKey['id'])) {
            throw new PrivateKeyFailedToSave('Failed to register recovery key.');
        }

        $copies = $this->resolveCopies();
        $outputDir = $this->resolveOutputDir();
        $sharePath = $outputDir.'/secret-stash-recovery-'.$fingerprint.'.txt';
        file_put_contents($sharePath, $share);
        chmod($sharePath, 0600);

        $pngPath = $outputDir.'/secret-stash-recovery-'.$fingerprint.'.png';
        $pngCreated = $this->writePngQr($share, $pngPath);

        $this->newLine();
        $this->line('<fg=green;options=bold>✓</> Recovery key created!');
        $this->line('<fg=yellow>Share:</> '.$share);
        $this->line('<fg=yellow>Saved:</> '.$sharePath);

        if ($pngCreated) {
            $this->line('<fg=yellow>QR PNG:</> '.$pngPath);
        } else {
            warning('QR PNG generation unavailable. Install `qrencode` to enable PNG output.');
        }

        for ($i = 1; $i <= $copies; $i++) {
            $this->newLine();
            $this->line('<fg=cyan;options=bold>Recovery Share Copy '.$i.'</>');
            $this->line($share);
            $ascii = $this->renderAsciiQr($share);
            if ($ascii) {
                $this->line($ascii);
            } else {
                warning('QR code rendering unavailable. Install `qrencode` to enable ASCII QR output.');
            }
        }

        $this->newLine();
        info('Store these recovery shares offline. Anyone with the share can decrypt your data.');

        return self::SUCCESS;
    }

    public function getPrivateKey(): string
    {
        $privateKey = $this->loadPrivateKey();

        if (! $privateKey) {
            throw new PrivateKeyNotFound;
        }

        return $privateKey;
    }

    public function getDeviceKeyId(): int
    {
        $metadata = $this->loadDeviceMetadata();

        if (! $metadata || ! isset($metadata['device_key_id'])) {
            throw new DeviceKeyNotRegistered;
        }

        return (int) $metadata['device_key_id'];
    }

    public function getDevicePublicKey(): string
    {
        $metadata = $this->loadDeviceMetadata();

        if (! $metadata || ! isset($metadata['public_key'])) {
            throw new DeviceKeyNotRegistered('Device public key not found. Run "secret-stash:keys init" first.');
        }

        return $metadata['public_key'];
    }

    protected function resolveDeviceLabel(): string
    {
        $default = $this->option('label') ?? (gethostname() ?: 'My Device');

        return text(
            label: 'Device label',
            placeholder: $default,
            default: $default,
            required: true
        );
    }

    protected function resolveCopies(): int
    {
        $copies = (int) ($this->option('copies') ?? 1);

        return max(1, $copies);
    }

    protected function resolveOutputDir(): string
    {
        $dir = $this->option('output-dir') ?? $this->path;

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return rtrim($dir, '/');
    }

    protected function hasLocalPrivateKey(): bool
    {
        return file_exists($this->privateKeyFile);
    }

    protected function loadPrivateKey(): ?string
    {
        if (! file_exists($this->privateKeyFile)) {
            return null;
        }

        $content = file_get_contents($this->privateKeyFile);

        return $content === false ? null : $content;
    }

    protected function savePrivateKey(string $privateKey): void
    {
        if (file_put_contents($this->privateKeyFile, $privateKey) === false) {
            throw new PrivateKeyFailedToSave;
        }

        chmod($this->privateKeyFile, 0600);
    }

    protected function loadDeviceMetadata(): ?array
    {
        if (! file_exists($this->deviceMetaFile)) {
            return null;
        }

        $content = file_get_contents($this->deviceMetaFile);
        if ($content === false) {
            return null;
        }

        $meta = json_decode($content, true);

        return is_array($meta) ? $meta : null;
    }

    protected function saveDeviceMetadata(array $metadata): void
    {
        $content = json_encode($metadata, JSON_PRETTY_PRINT);
        if (file_put_contents($this->deviceMetaFile, $content) === false) {
            throw new MetaKeyFailedToSave;
        }

        chmod($this->deviceMetaFile, 0600);
    }

    protected function renderAsciiQr(string $data): ?string
    {
        try {
            $writer = new SvgWriter;
            $result = $writer->write($this->createQrCode($data));
            $matrix = $result->getMatrix();

            return $this->matrixToAscii($matrix);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function writePngQr(string $data, string $path): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }

        try {
            $writer = new PngWriter;
            $result = $writer->write($this->createQrCode($data));
            $result->saveToFile($path);

            return file_exists($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function writeSvgQr(string $data, string $path): bool
    {
        try {
            $writer = new SvgWriter;
            $result = $writer->write($this->createQrCode($data));
            $result->saveToFile($path);

            return file_exists($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function createQrCode(string $data): QrCode
    {
        return QrCode::create($data)
            ->setSize(320)
            ->setMargin(1);
    }

    protected function matrixToAscii(MatrixInterface $matrix): string
    {
        $rows = [];
        $size = $matrix->getBlockCount();
        $quietZone = 2;

        for ($row = -$quietZone; $row < $size + $quietZone; $row++) {
            $line = '';
            for ($col = -$quietZone; $col < $size + $quietZone; $col++) {
                $isDark = $row >= 0 && $col >= 0 && $row < $size && $col < $size
                    ? $matrix->getBlockValue($row, $col) === 1
                    : false;

                $line .= $isDark ? '██' : '  ';
            }
            $rows[] = $line;
        }

        return implode(PHP_EOL, $rows);
    }
}
