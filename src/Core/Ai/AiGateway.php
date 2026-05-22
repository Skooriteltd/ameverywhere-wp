<?php

namespace RankSavvy\Core\Ai;

class AiGateway
{
    private string $encryptionKey;

    public function __construct()
    {
        // Obtain a unique, site-specific key derived from WordPress salts.
        // If salts are undefined, fallback to site URL to ensure database consistency.
        if (defined('SECURE_AUTH_KEY') && !empty(SECURE_AUTH_KEY)) {
            $this->encryptionKey = SECURE_AUTH_KEY;
        } elseif (function_exists('wp_salt')) {
            $this->encryptionKey = wp_salt('auth');
        } else {
            $this->encryptionKey = hash('sha256', get_bloginfo('url') . 'ranksavvy_salt');
        }
    }

    /**
     * Encrypt sensitive data using AES-256-CBC.
     */
    public function encrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }
        $cipher = 'aes-256-cbc';
        $ivLen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivLen);
        $encrypted = openssl_encrypt($data, $cipher, $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data using AES-256-CBC.
     */
    public function decrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }
        $cipher = 'aes-256-cbc';
        $decoded = base64_decode($data);
        $ivLen = openssl_cipher_iv_length($cipher);
        
        if (strlen($decoded) <= $ivLen) {
            return '';
        }
        
        $iv = substr($decoded, 0, $ivLen);
        $encrypted = substr($decoded, $ivLen);
        $decrypted = openssl_decrypt($encrypted, $cipher, $this->encryptionKey, 0, $iv);
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Route generative prompts dynamically to the selected AI provider.
     */
    public function queryModel(string $prompt, string $systemPrompt = 'You are a helpful SEO writing assistant.'): array
    {
        $provider = get_option('ranksavvy_ai_provider', 'openai');
        
        switch ($provider) {
            case 'openai':
                return $this->queryOpenAi($prompt, $systemPrompt);
            case 'anthropic':
                return $this->queryAnthropic($prompt, $systemPrompt);
            case 'ollama':
                return $this->queryOllama($prompt, $systemPrompt);
            default:
                return [
                    'success' => false,
                    'message' => __('Invalid AI provider selected.', 'ranksavvy')
                ];
        }
    }

    /**
     * Query OpenAI Chat Completions API.
     */
    private function queryOpenAi(string $prompt, string $systemPrompt): array
    {
        $encryptedKey = get_option('ranksavvy_openai_key', '');
        $apiKey = $this->decrypt($encryptedKey);

        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => __('OpenAI API key is missing or not configured.', 'ranksavvy')
            ];
        }

        $url = 'https://api.openai.com/v1/chat/completions';
        $body = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1000
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $data = json_decode($responseBody, true);

        if ($responseCode !== 200) {
            $errMsg = isset($data['error']['message']) ? $data['error']['message'] : __('OpenAI API Error.', 'ranksavvy');
            return [
                'success' => false,
                'message' => $errMsg
            ];
        }

        $content = isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : '';
        return [
            'success' => true,
            'text' => trim($content)
        ];
    }

    /**
     * Query Anthropic Messages API.
     */
    private function queryAnthropic(string $prompt, string $systemPrompt): array
    {
        $encryptedKey = get_option('ranksavvy_anthropic_key', '');
        $apiKey = $this->decrypt($encryptedKey);

        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => __('Anthropic API key is missing or not configured.', 'ranksavvy')
            ];
        }

        $url = 'https://api.anthropic.com/v1/messages';
        $body = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 1000,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $data = json_decode($responseBody, true);

        if ($responseCode !== 200) {
            $errMsg = isset($data['error']['message']) ? $data['error']['message'] : __('Anthropic API Error.', 'ranksavvy');
            return [
                'success' => false,
                'message' => $errMsg
            ];
        }

        $content = isset($data['content'][0]['text']) ? $data['content'][0]['text'] : '';
        return [
            'success' => true,
            'text' => trim($content)
        ];
    }

    /**
     * Query local Ollama API.
     */
    private function queryOllama(string $prompt, string $systemPrompt): array
    {
        $ollamaUrl = get_option('ranksavvy_ollama_url', 'http://localhost:11434');
        $ollamaUrl = rtrim($ollamaUrl, '/');
        
        $url = $ollamaUrl . '/api/chat';
        $body = [
            'model' => 'llama3', // sensible default model
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'options' => [
                'temperature' => 0.7
            ],
            'stream' => false
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(__('Ollama Connection Error: %s', 'ranksavvy'), $response->get_error_message())
            ];
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $data = json_decode($responseBody, true);

        if ($responseCode !== 200) {
            return [
                'success' => false,
                'message' => __('Ollama local instance returned an error.', 'ranksavvy')
            ];
        }

        $content = isset($data['message']['content']) ? $data['message']['content'] : '';
        return [
            'success' => true,
            'text' => trim($content)
        ];
    }
}
