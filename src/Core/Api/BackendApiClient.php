<?php

namespace AmEveryWhere\Core\Api;

if (!defined('ABSPATH')) {
    exit;
}

use AmEveryWhere\Core\Security\KeyVault;

/**
 * BackendApiClient
 *
 * Central HTTP client connecting the AmEveryWhere WordPress plugin (Distribution &
 * Data-Collection Layer) to the AmEveryWhere Backend API.
 * Offloads heavy computational workloads (AI generation, full-site auditing,
 * content gap analysis, keyword cannibalization, and rank tracking).
 */
class BackendApiClient
{
    private const DEFAULT_API_URL = 'https://api.ameverywhere.com/v1';
    private const TIMEOUT = 30;

    /**
     * Get the configured Backend API base URL.
     */
    public function getApiUrl(): string
    {
        $url = get_option('ameverywhere_backend_api_url', self::DEFAULT_API_URL);
        return untrailingslashit(!empty($url) ? $url : self::DEFAULT_API_URL);
    }

    /**
     * Get the decrypted API key for AmEveryWhere backend.
     */
    public function getApiKey(): string
    {
        $encryptedKey = get_option('ameverywhere_api_key', '');

        if (empty($encryptedKey)) {
            return '';
        }

        return KeyVault::decrypt($encryptedKey);
    }

    /**
     * Determine if the remote backend API is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    /**
     * Test connection to the AmEveryWhere Backend API.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => __('No AmEveryWhere API key configured. Operating in local / BYOK fallback mode.', 'ameverywhere'),
                'mode'    => 'fallback',
            ];
        }

        $result = $this->get('/health');
        if (!empty($result['success'])) {
            return [
                'success' => true,
                'message' => __('Successfully connected to AmEveryWhere Backend API.', 'ameverywhere'),
                'mode'    => 'cloud',
                'data'    => $result['data'] ?? [],
            ];
        }

        return [
            'success' => false,
            'message' => $result['message'] ?? __('Failed to connect to AmEveryWhere Backend API.', 'ameverywhere'),
            'mode'    => 'fallback',
        ];
    }

    /**
     * Send a POST request to the Backend API.
     */
    public function post(string $endpoint, array $payload = []): array
    {
        $url = $this->getApiUrl() . '/' . ltrim($endpoint, '/');
        $apiKey = $this->getApiKey();

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'User-Agent'   => 'AmEveryWhere-WP/' . (defined('AMEVERYWHERE_VERSION') ? AMEVERYWHERE_VERSION : '1.0.0'),
        ];

        if (!empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        // Include client origin metadata for license/rate-limit verification
        $payload['site_url'] = get_site_url();

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => wp_json_encode($payload),
            'timeout' => self::TIMEOUT,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Send a GET request to the Backend API.
     */
    public function get(string $endpoint, array $params = []): array
    {
        $url = $this->getApiUrl() . '/' . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $apiKey = $this->getApiKey();

        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => 'AmEveryWhere-WP/' . (defined('AMEVERYWHERE_VERSION') ? AMEVERYWHERE_VERSION : '1.0.0'),
        ];

        if (!empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => self::TIMEOUT,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Offload AI Generation request to Backend API.
     */
    public function generateAi(array $payload): array
    {
        if (!$this->isConfigured()) {
            return [
                'success'  => false,
                'fallback' => true,
                'message'  => __('AmEveryWhere Backend API key is not set; falling back to BYOK provider.', 'ameverywhere'),
            ];
        }

        return $this->post('/ai/generate', $payload);
    }

    /**
     * Offload Content Gap Analysis to Backend API.
     */
    public function analyzeContentGap(string $keyword, array $existingTopics): array
    {
        if (!$this->isConfigured()) {
            return [
                'success'  => false,
                'fallback' => true,
                'message'  => __('Backend API key not configured; falling back to local processing.', 'ameverywhere'),
            ];
        }

        return $this->post('/analysis/content-gap', [
            'keyword'         => $keyword,
            'existing_topics' => $existingTopics,
        ]);
    }

    /**
     * Offload Keyword Cannibalization computation to Backend API.
     */
    public function analyzeCannibalization(array $postKeywordMap): array
    {
        if (!$this->isConfigured()) {
            return [
                'success'  => false,
                'fallback' => true,
                'message'  => __('Backend API key not configured; falling back to local matrix processing.', 'ameverywhere'),
            ];
        }

        return $this->post('/analysis/cannibalization', [
            'posts' => $postKeywordMap,
        ]);
    }

    /**
     * Offload Technical SEO Site Audit to Backend API.
     */
    public function dispatchTechnicalAudit(array $siteMetadata): array
    {
        if (!$this->isConfigured()) {
            return [
                'success'  => false,
                'fallback' => true,
                'message'  => __('Backend API key not configured; running audit locally via WP-Cron.', 'ameverywhere'),
            ];
        }

        return $this->post('/audit/technical/dispatch', [
            'site' => $siteMetadata,
        ]);
    }

    /**
     * Parse HTTP response into standard associative array.
     */
    private function handleResponse($response): array
    {
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'data'    => $data,
            ];
        }

        $errorMessage = $data['message'] ?? $data['error'] ?? sprintf(__('API returned error code %d', 'ameverywhere'), $code);
        return [
            'success' => false,
            'message' => $errorMessage,
            'code'    => $code,
        ];
    }
}
