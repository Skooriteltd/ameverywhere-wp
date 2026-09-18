<?php

namespace AmEveryWhere\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AmEveryWhere\Core\Security\KeyVault;

class KeyVaultTest extends TestCase
{
    public function testEncryptAndDecryptRoundTrip(): void
    {
        $secret = 'sk-proj-test1234567890abcdefghijklmnopqrstuvwxyz';
        $encrypted = KeyVault::encrypt($secret);

        $this->assertNotEmpty($encrypted);
        $this->assertStringStartsWith('aew_enc::', $encrypted);
        $this->assertNotEquals($secret, $encrypted);

        $decrypted = KeyVault::decrypt($encrypted);
        $this->assertSame($secret, $decrypted);
    }

    public function testEmptyStringHandling(): void
    {
        $this->assertSame('', KeyVault::encrypt(''));
        $this->assertSame('', KeyVault::decrypt(''));
    }

    public function testDoesNotDoubleEncrypt(): void
    {
        $secret = 'sk-proj-single-encryption';
        $encryptedOnce = KeyVault::encrypt($secret);
        $encryptedTwice = KeyVault::encrypt($encryptedOnce);

        $this->assertSame($encryptedOnce, $encryptedTwice);
    }

    public function testLegacyUnencryptedKeyPassThrough(): void
    {
        $legacyKey = 'plain-text-api-key-without-prefix';
        $decrypted = KeyVault::decrypt($legacyKey);

        $this->assertSame($legacyKey, $decrypted);
    }

    public function testTamperedCiphertextFailsDecryption(): void
    {
        $secret = 'my-super-confidential-service-account-key';
        $encrypted = KeyVault::encrypt($secret);

        // Tamper with the base64 content
        $payload = substr($encrypted, strlen('aew_enc::'));
        $decoded = base64_decode($payload);
        
        // Flip one byte in the ciphertext portion
        $tamperedRaw = $decoded;
        $tamperedRaw[strlen($tamperedRaw) - 1] = chr(ord($tamperedRaw[strlen($tamperedRaw) - 1]) ^ 0xFF);
        $tamperedEncrypted = 'aew_enc::' . base64_encode($tamperedRaw);

        // Should return empty string due to HMAC mismatch
        $result = KeyVault::decrypt($tamperedEncrypted);
        $this->assertSame('', $result);
    }
}
