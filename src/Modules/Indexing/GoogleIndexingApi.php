<?php

namespace RankSavvy\Modules\Indexing;

class GoogleIndexingApi
{
    public function ping(string $url, string $action): bool
    {
        $apiKeyJsonStr = get_option('ranksavvy_google_indexing_key');
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

    private function getAccessToken(array $jsonKey): ?string
    {
        $transientKey = 'ranksavvy_gsc_token';
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
        $apiKeyJsonStr = get_option('ranksavvy_google_indexing_key');
        if (empty($apiKeyJsonStr)) {
            return ['error' => 'API Key is missing or empty. Please set it in options.'];
        }

        $jsonKey = json_decode($apiKeyJsonStr, true);
        if (!$jsonKey || !isset($jsonKey['private_key']) || !isset($jsonKey['client_email'])) {
            return ['error' => 'Invalid API key format.'];
        }

        $transientKey = 'ranksavvy_gsc_meta_' . md5($url);
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

