<?php

namespace AmEveryWhere\Modules\Indexing;

use AmEveryWhere\Core\Security\KeyVault;

class GoogleIndexingApi
{
    private function getStoredCredentials(): string
    {
        $raw = (string) get_option('ameverywhere_google_indexing_key', '');
        return KeyVault::decrypt($raw);
    }

    public function ping(string $url, string $action): bool
    {
        $apiKeyJsonStr = $this->getStoredCredentials();
        if (empty($apiKeyJsonStr)) {
            return false;
        }

        $jsonKey = json_decode($apiKeyJsonStr, true);
        if (!$jsonKey || !isset($jsonKey['private_key']) || !isset($jsonKey['client_email'])) {
            return false;
        }

        $accessToken = $this->getAccessToken($jsonKey);
        if (!$accessToken) {
            return false;
        }

        $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
        
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'body' => wp_json_encode([
                'url'  => $url,
                'type' => $action
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code === 200 || $code === 202;
    }

    /**
     * Retrieve a valid Google OAuth2 access token.
     *
     * When called with a $jsonKey array the key is used directly (internal use).
     * When called with no arguments the key is read from the stored option, which
     * allows external callers (e.g. AdminModule) to obtain a token without
     * duplicating the credential-loading logic.
     *
     * @param array|null $jsonKey Service-account credentials array, or null to auto-load.
     * @return string|null        Bearer token string, or null on failure.
     */
    public function getAccessToken(?array $jsonKey = null): ?string
    {
        if ($jsonKey === null) {
            $apiKeyJsonStr = $this->getStoredCredentials();
            if (empty($apiKeyJsonStr)) {
                return null;
            }
            $jsonKey = json_decode($apiKeyJsonStr, true);
            if (!$jsonKey || !isset($jsonKey['private_key'], $jsonKey['client_email'])) {
                return null;
            }
        }

        $transientKey = 'ameverywhere_gsc_token';
        $token = get_transient($transientKey);
        if ($token) {
            return $token;
        }

        $header = wp_json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $claim = wp_json_encode([
            'iss'   => $jsonKey['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlClaim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));
        
        $signature = '';
        if (!openssl_sign($base64UrlHeader . "." . $base64UrlClaim, $signature, $jsonKey['private_key'], 'SHA256')) {
            return null;
        }
        
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $base64UrlHeader . "." . $base64UrlClaim . "." . $base64UrlSignature;

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt
            ]
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['access_token'])) {
            set_transient($transientKey, $body['access_token'], 3500); // Cache for slightly less than 1 hour
            return $body['access_token'];
        }

        return null;
    }

    public function checkStatus(string $url): array
    {
        $apiKeyJsonStr = $this->getStoredCredentials();
        if (empty($apiKeyJsonStr)) {
            return ['error' => 'API Key is missing or empty. Please set it in options.'];
        }

        $jsonKey = json_decode($apiKeyJsonStr, true);
        if (!$jsonKey || !isset($jsonKey['private_key']) || !isset($jsonKey['client_email'])) {
            return ['error' => 'Invalid API key format.'];
        }

        $transientKey = 'ameverywhere_gsc_meta_' . md5($url);
        $cached = get_transient($transientKey);
        if ($cached !== false) {
            return $cached;
        }

        $accessToken = $this->getAccessToken($jsonKey);
        if (!$accessToken) {
            return ['error' => 'Failed to obtain access token.'];
        }

        $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications/metadata?url=' . urlencode($url);

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $errorMsg = isset($data['error']['message']) ? $data['error']['message'] : 'HTTP Code ' . $code;
            return ['error' => $errorMsg];
        }

        set_transient($transientKey, $data, 300); // 5 minutes cache

        return $data;
    }
}

