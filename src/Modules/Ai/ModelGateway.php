<?php

namespace RankSavvy\Modules\Ai;

use RankSavvy\Core\Security\KeyVault;

/**
 * ModelGateway: Unified abstraction layer for routing prompts across
 * multiple AI providers (OpenAI, Anthropic, Ollama local).
 *
 * Implements graceful fallback: tries providers in order until one succeeds.
 */
class ModelGateway
{
    private array $settings;

    public function __construct()
    {
        $raw = get_option('ranksavvy_settings', []);
        $this->settings = KeyVault::decryptSettings(is_array($raw) ? $raw : []);
    }

    /**
     * Send a completion prompt to the best available provider.
     *
     * @param string $prompt     The user/system prompt.
     * @param array  $options    Optional overrides: max_tokens, temperature, provider.
     * @return array{success: bool, content: string, provider: string, error?: string}
     */
    public function complete(string $prompt, array $options = []): array
    {
        $preferredProvider = $options['provider'] ?? $this->detectPreferredProvider();
        $providers         = $this->buildProviderChain($preferredProvider);

        foreach ($providers as $provider) {
            $result = $this->dispatch($provider, $prompt, $options);
            if ($result['success']) {
                return $result;
            }
        }

        return [
            'success'  => false,
            'content'  => '',
            'provider' => 'none',
            'error'    => 'All configured AI providers failed to respond. Please check your API keys.',
        ];
    }

    /**
     * Test connectivity to a specific provider without sending real prompts.
     */
    public function testConnection(string $provider): array
    {
        return $this->dispatch($provider, 'Say "OK" to confirm the connection works.', ['max_tokens' => 10]);
    }

    // ─────────────────────────────────────────────
    //  Private Helpers
    // ─────────────────────────────────────────────

    private function detectPreferredProvider(): string
    {
        if (!empty($this->settings['openai_api_key'])) {
            return 'openai';
        }
        if (!empty($this->settings['anthropic_api_key'])) {
            return 'anthropic';
        }
        if (!empty($this->settings['ollama_endpoint'])) {
            return 'ollama';
        }
        return 'openai';
    }

    private function buildProviderChain(string $preferred): array
    {
        $all   = ['openai', 'anthropic', 'ollama'];
        $chain = array_filter($all, fn($p) => $p !== $preferred);
        return array_merge([$preferred], array_values($chain));
    }

    private function dispatch(string $provider, string $prompt, array $options): array
    {
        return match ($provider) {
            'openai'    => $this->callOpenAi($prompt, $options),
            'anthropic' => $this->callAnthropic($prompt, $options),
            'ollama'    => $this->callOllama($prompt, $options),
            default     => ['success' => false, 'content' => '', 'provider' => $provider, 'error' => 'Unknown provider.'],
        };
    }

    private function callOpenAi(string $prompt, array $options): array
    {
        $apiKey = $this->settings['openai_api_key'] ?? '';
        if (empty($apiKey)) {
            return ['success' => false, 'content' => '', 'provider' => 'openai', 'error' => 'OpenAI API key not configured.'];
        }

        $model       = $options['model'] ?? 'gpt-4o-mini';
        $maxTokens   = $options['max_tokens'] ?? 1024;
        $temperature = $options['temperature'] ?? 0.7;

        $body = wp_json_encode([
            'model'       => $model,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
        ]);

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'content' => '', 'provider' => 'openai', 'error' => $response->get_error_message()];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            $errorMsg = $data['error']['message'] ?? 'Invalid response from OpenAI.';
            return ['success' => false, 'content' => '', 'provider' => 'openai', 'error' => $errorMsg];
        }

        return ['success' => true, 'content' => trim($content), 'provider' => 'openai'];
    }

    private function callAnthropic(string $prompt, array $options): array
    {
        $apiKey = $this->settings['anthropic_api_key'] ?? '';
        if (empty($apiKey)) {
            return ['success' => false, 'content' => '', 'provider' => 'anthropic', 'error' => 'Anthropic API key not configured.'];
        }

        $model     = $options['model'] ?? 'claude-3-haiku-20240307';
        $maxTokens = $options['max_tokens'] ?? 1024;

        $body = wp_json_encode([
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
            'headers' => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'content' => '', 'provider' => 'anthropic', 'error' => $response->get_error_message()];
        }

        $data    = json_decode(wp_remote_retrieve_body($response), true);
        $content = $data['content'][0]['text'] ?? null;

        if ($content === null) {
            $errorMsg = $data['error']['message'] ?? 'Invalid response from Anthropic.';
            return ['success' => false, 'content' => '', 'provider' => 'anthropic', 'error' => $errorMsg];
        }

        return ['success' => true, 'content' => trim($content), 'provider' => 'anthropic'];
    }

    private function callOllama(string $prompt, array $options): array
    {
        $endpoint = rtrim($this->settings['ollama_endpoint'] ?? '', '/');
        if (empty($endpoint)) {
            return ['success' => false, 'content' => '', 'provider' => 'ollama', 'error' => 'Ollama endpoint not configured.'];
        }

        $model = $options['model'] ?? ($this->settings['ollama_model'] ?? 'llama3');

        $body = wp_json_encode([
            'model'  => $model,
            'prompt' => $prompt,
            'stream' => false,
        ]);

        $response = wp_remote_post($endpoint . '/api/generate', [
            'timeout' => 60,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'content' => '', 'provider' => 'ollama', 'error' => $response->get_error_message()];
        }

        $data    = json_decode(wp_remote_retrieve_body($response), true);
        $content = $data['response'] ?? null;

        if ($content === null) {
            return ['success' => false, 'content' => '', 'provider' => 'ollama', 'error' => 'Invalid response from Ollama.'];
        }

        return ['success' => true, 'content' => trim($content), 'provider' => 'ollama'];
    }
}
