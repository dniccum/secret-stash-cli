<?php

namespace Dniccum\SecretStash\Crypto;

class CryptoHelper
{
    public static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function aesGcmEncrypt(string $plaintext, string $key): array
    {
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException('Key must be 32 bytes (256 bits)');
        }

        $iv = random_bytes(12);
        $salt = random_bytes(16);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return [
            'v' => 1,
            'alg' => 'AES-GCM',
            'kdf' => 'none',
            'iter' => 0,
            'salt' => self::base64urlEncode($salt),
            'iv' => self::base64urlEncode($iv),
            'tag' => self::base64urlEncode($tag),
            'ct' => self::base64urlEncode($ciphertext),
        ];
    }

    public static function aesGcmDecrypt(array $payload, string $key): string
    {
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException('Key must be 32 bytes (256 bits)');
        }

        if (! isset($payload['alg']) || $payload['alg'] !== 'AES-GCM') {
            throw new \InvalidArgumentException('Unsupported algorithm');
        }

        $iv = self::base64urlDecode($payload['iv']);
        $tag = self::base64urlDecode($payload['tag']);
        $ciphertext = self::base64urlDecode($payload['ct']);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $plaintext;
    }

    public static function generateKey(): string
    {
        return random_bytes(32);
    }

    /**
     * Generate RSA-4096 key pair for user encryption.
     */
    public static function generateRSAKeyPair(): array
    {
        $config = [
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        if ($res === false) {
            throw new \RuntimeException('Failed to generate RSA key pair');
        }

        // Export private key
        openssl_pkey_export($res, $privateKey);

        // Export public key
        $publicKeyDetails = openssl_pkey_get_details($res);
        $publicKey = $publicKeyDetails['key'];

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    /**
     * Encrypt private key with password using PBKDF2 + AES-GCM.
     */
    public static function encryptPrivateKey(string $privateKey, string $password): array
    {
        $salt = random_bytes(16);
        $iterations = 600000;

        // Derive key from password
        $derivedKey = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);

        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $privateKey,
            'aes-256-gcm',
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Failed to encrypt private key');
        }

        return [
            'v' => 1,
            'alg' => 'AES-256-GCM',
            'kdf' => 'PBKDF2',
            'iter' => $iterations,
            'salt' => self::base64urlEncode($salt),
            'iv' => self::base64urlEncode($iv),
            'tag' => self::base64urlEncode($tag),
            'ct' => self::base64urlEncode($ciphertext),
        ];
    }

    /**
     * Decrypt private key with password.
     */
    public static function decryptPrivateKey(array $payload, string $password): string
    {
        if (! isset($payload['kdf']) || $payload['kdf'] !== 'PBKDF2') {
            throw new \InvalidArgumentException('Unsupported KDF');
        }

        $salt = self::base64urlDecode($payload['salt']);
        $iterations = $payload['iter'];

        // Derive key from password
        $derivedKey = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);

        $iv = self::base64urlDecode($payload['iv']);
        $tag = self::base64urlDecode($payload['tag']);
        $ciphertext = self::base64urlDecode($payload['ct']);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Failed to decrypt private key - incorrect password?');
        }

        return $plaintext;
    }

    /**
     * Encrypt data with RSA public key (for creating envelopes).
     */
    public static function rsaEncrypt(string $data, string $publicKey): string
    {
        $key = openssl_pkey_get_public($publicKey);
        if ($key === false) {
            throw new \InvalidArgumentException('Invalid public key');
        }

        $encrypted = '';
        $success = openssl_public_encrypt(
            $data,
            $encrypted,
            $key,
            OPENSSL_PKCS1_OAEP_PADDING
        );

        if (! $success) {
            throw new \RuntimeException('RSA encryption failed');
        }

        return $encrypted;
    }

    /**
     * Decrypt data with RSA private key (for reading envelopes).
     */
    public static function rsaDecrypt(string $encryptedData, string $privateKey): string
    {
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new \InvalidArgumentException('Invalid private key');
        }

        $decrypted = '';
        $success = openssl_private_decrypt(
            $encryptedData,
            $decrypted,
            $key,
            OPENSSL_PKCS1_OAEP_PADDING
        );

        if (! $success) {
            throw new \RuntimeException('RSA decryption failed');
        }

        return $decrypted;
    }

    /**
     * Create an envelope: encrypt DEK with user's public key.
     */
    public static function createEnvelope(string $dek, string $publicKey): array
    {
        $encryptedDEK = self::rsaEncrypt($dek, $publicKey);

        return [
            'v' => 1,
            'alg' => 'RSA-OAEP',
            'ct' => self::base64urlEncode($encryptedDEK),
        ];
    }

    /**
     * Open an envelope: decrypt DEK with user's private key.
     */
    public static function openEnvelope(array $envelope, string $privateKey): string
    {
        if (! isset($envelope['alg']) || $envelope['alg'] !== 'RSA-OAEP') {
            throw new \InvalidArgumentException('Unsupported envelope algorithm');
        }

        $encryptedDEK = self::base64urlDecode($envelope['ct']);

        return self::rsaDecrypt($encryptedDEK, $privateKey);
    }
}
