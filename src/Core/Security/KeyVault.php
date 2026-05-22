<?php

namespace RankSavvy\Core\Security;

/**
 * KeyVault: Encrypts and decrypts sensitive API keys using AES-256-CBC.
 *
 * Encryption key is derived from a persistent, site-specific salt stored in
 * wp_options upon activation. This ensures the master key is STABLE across
 * URL migrations (staging → production), unlike the previous approach of
 * using get_site_url() which would invalidate all stored credentials on migration.
 *
 * Salt derivation order:
 *   1. ranksavvy_encryption_salt (dedicated persistent option — preferred)
 *   2. AUTH_KEY constant (WordPress secret key — fallback)
 *   3. Hard-coded fallback (last resort — warns admin to configure secrets)
 */
class KeyVault
{
    private const CIPHER      = 'aes-256-cbc';
    private const PREFIX      = 'rs_enc::';
    private const SALT_OPTION = 'ranksavvy_encryption_salt';

    /**
     * Generate and persist a unique encryption salt on plugin activation.
     * Must be called from Plugin::activate() to ensure the salt exists
     * before any credentials are encrypted.
     */
    public static function ensureSaltExists(): void
    {
        if (get_option(self::SALT_OPTION)) {
            return; // Already generated — do not overwrite
        }

        // Generate 64 cryptographically random hex characters (256 bits of entropy)
        $salt = bin2hex(random_bytes(32));
        add_option(self::SALT_OPTION, $salt, '', false); // autoload=false (not needed on frontend)
    }

    /**
     * Derive a 256-bit encryption key unique to this WordPress installation.
     * Uses the persistent dedicated salt so the key survives URL migrations.
     */
    private static function deriveMasterKey(): string
    {
        // 1. Dedicated persistent salt (stable across URL changes)
        $salt = get_option(self::SALT_OPTION, '');

        // 2. Fall back to AUTH_KEY if option is missing (e.g. before first activation)
        if (empty($salt)) {
            $salt = defined('AUTH_KEY') ? AUTH_KEY : 'ranksavvy-fallback-key-change-me';
        }

        return hash('sha256', $salt, true); // 32 raw bytes
    }

    /**
     * Encrypt a plaintext string. Returns a base64-encoded cipher string with IV prefix.
     */
    public static function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            return '';
        }

        // Avoid double-encrypting already-encrypted values
        if (str_starts_with($plaintext, self::PREFIX)) {
            return $plaintext;
        }

        $key   = self::deriveMasterKey();
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv    = openssl_random_pseudo_bytes($ivLen);

        $cipherRaw = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($cipherRaw === false) {
            return $plaintext; // Fallback: return plaintext if encryption fails
        }

        $hmac = hash_hmac('sha256', $cipherRaw, $key, true);

        return self::PREFIX . base64_encode($iv . $hmac . $cipherRaw);
    }

    /**
     * Decrypt a previously encrypted string.
     */
    public static function decrypt(string $encrypted): string
    {
        if (empty($encrypted)) {
            return '';
        }

        // Not encrypted by us — return as-is (handles legacy plain-text keys)
        if (!str_starts_with($encrypted, self::PREFIX)) {
            return $encrypted;
        }

        $key     = self::deriveMasterKey();
        $decoded = base64_decode(substr($encrypted, strlen(self::PREFIX)));

        $ivLen   = openssl_cipher_iv_length(self::CIPHER);
        $hmacLen = 32;

        if (strlen($decoded) < $ivLen + $hmacLen) {
            return '';
        }

        $iv        = substr($decoded, 0, $ivLen);
        $hmac      = substr($decoded, $ivLen, $hmacLen);
        $cipherRaw = substr($decoded, $ivLen + $hmacLen);

        // Verify HMAC integrity before decrypting
        $expectedHmac = hash_hmac('sha256', $cipherRaw, $key, true);
        if (!hash_equals($expectedHmac, $hmac)) {
            return ''; // Tampered or corrupt data
        }

        $plaintext = openssl_decrypt($cipherRaw, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        return $plaintext === false ? '' : $plaintext;
    }

    /**
     * Encrypt all known sensitive keys in a settings array before persisting to DB.
     */
    public static function encryptSettings(array $settings): array
    {
        foreach (self::sensitiveKeys() as $key) {
            if (!empty($settings[$key])) {
                $settings[$key] = self::encrypt($settings[$key]);
            }
        }
        return $settings;
    }

    /**
     * Decrypt all known sensitive keys in a settings array after reading from DB.
     */
    public static function decryptSettings(array $settings): array
    {
        foreach (self::sensitiveKeys() as $key) {
            if (!empty($settings[$key])) {
                $settings[$key] = self::decrypt($settings[$key]);
            }
        }
        return $settings;
    }

    /**
     * List of settings keys that contain sensitive API credentials.
     */
    private static function sensitiveKeys(): array
    {
        return [
            'openai_api_key',
            'anthropic_api_key',
            'gsc_api_key',
            'bing_api_key',
        ];
    }
}
