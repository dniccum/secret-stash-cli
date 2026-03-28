<?php

use Dniccum\SecretStash\Crypto\CryptoHelper;
use Dniccum\SecretStash\Tests\TestCase;

uses(TestCase::class)->in('Feature')
    ->beforeEach(function () {
        // Create RSA Pair
        $this->dir = sys_get_temp_dir().'/.secret-stash';
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
        $pair = CryptoHelper::generateRSAKeyPair();
        file_put_contents($this->dir.'/device_private_key.pem', $pair->private_key);
        file_put_contents($this->dir.'/device.json', json_encode([
            'device_key_id' => 123,
            'label' => 'Test Device',
            'public_key' => $pair->public_key,
            'fingerprint' => CryptoHelper::fingerprint($pair->public_key),
        ]));

        $this->pair = $pair;
    });
