<?php

namespace Dniccum\Vaultr\Crypto;

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
}
