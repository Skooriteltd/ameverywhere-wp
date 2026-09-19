<?php

namespace AmEveryWhere\Modules\ContentAssistant;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LlmWritingAssistant
 *
 * BL-013: Real-time keyword density feedback and LLM-powered content suggestions.
 * Primary interaction is on-demand (button) to prevent API flooding.
 * Minimum 3000ms debounce for any auto-analysis.
 */
class LlmWritingAssistant
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
    }

    public function enqueueEditorAssets(): void
    {
        // Pass REST data to editor JS — actual sidebar plugin is React-side
        wp_add_inline_script(
            'wp-blocks',
            'window.amEveryWhereWritingAssistant = ' . wp_json_encode([
                'apiBase' => rest_url('ameverywhere/v1'),
                'nonce'   => wp_create_nonce('wp_rest'),
            ]) . ';',
            'before'
        );
    }

    public function registerRoutes(): void
    {
        $editCap = fn() => current_user_can('edit_posts');

        // Keyword density analysis
        register_rest_route('ameverywhere/v1', '/writing/keyword-density', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'analyseKeywordDensity'],
            'permission_callback' => $editCap,
        ]);

        // Improve a paragraph via LLM
        register_rest_route('ameverywhere/v1', '/writing/improve-paragraph', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'improveParagraph'],
            'permission_callback' => $editCap,
        ]);

        // Suggest meta title and description from content
        register_rest_route('ameverywhere/v1', '/writing/suggest-meta', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'suggestMeta'],
            'permission_callback' => $editCap,
        ]);

        // List prompt templates per content type
        register_rest_route('ameverywhere/v1', '/writing/prompt-templates', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getPromptTemplates'],
            'permission_callback' => $editCap,
        ]);
    }

    // ── Keyword density (no LLM — local analysis) ─────────────────────────────

    public function analyseKeywordDensity(\WP_REST_Request $request): \WP_REST_Response
    {
        $params  = $request->get_json_params();
        $postId  = absint($params['post_id'] ?? 0);
        $keyword = sanitize_text_field($params['keyword'] ?? '');
        $content = sanitize_textarea_field($params['content'] ?? '');

        if ($postId && empty($keyword)) {
            $keyword = (string) get_post_meta($postId, '_ameverywhere_focus_keyword', true);
        }
        if ($postId && empty($content)) {
            $content = wp_strip_all_tags(get_post_field('post_content', $postId));
        }

        if (empty($keyword) || empty($content)) {
            return new \WP_Error('missing_input', 'keyword and content are required.', ['status' => 400]);
        }

        $words      = preg_split('/\s+/', mb_strtolower(trim($content)));
        $words      = array_filter($words, fn($w) => mb_strlen($w) > 1);
        $total      = count($words);
        $kwLower    = mb_strtolower($keyword);
        $exactCount = 0;

        foreach ($words as $word) {
            $clean = preg_replace('/[^a-z0-9\-]/', '', $word);
            if ($clean === $kwLower || mb_strpos($clean, $kwLower) !== false) {
                $exactCount++;
            }
        }

        $density = $total > 0 ? round(($exactCount / $total) * 100, 2) : 0.0;

        return rest_ensure_response([
            'keyword'       => $keyword,
            'total_words'   => $total,
            'occurrences'   => $exactCount,
            'density'       => $density,
            'status'        => $density < 0.5 ? 'low' : ($density > 3 ? 'high' : 'good'),
            'recommendation' => $density < 0.5
                ? 'Add more natural mentions of the focus keyword.'
                : ($density > 3 ? 'Reduce keyword repetition to avoid stuffing.' : 'Keyword density is in the ideal 0.5%–3% range. ✅'),
        ]);
    }

    // ── Improve paragraph via LLM ─────────────────────────────────────────────

    public function improveParagraph(\WP_REST_Request $request): \WP_REST_Response
    {
        $params    = $request->get_json_params();
        $paragraph = sanitize_textarea_field($params['paragraph'] ?? '');
        $keyword   = sanitize_text_field($params['keyword'] ?? '');
        $tone      = sanitize_text_field($params['tone'] ?? 'professional');

        if (empty($paragraph)) {
            return new \WP_Error('missing_paragraph', 'paragraph is required.', ['status' => 400]);
        }

        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return new \WP_Error('no_api_key', 'No LLM API key configured.', ['status' => 400]);
        }

        $kwInstruction = $keyword ? "Naturally incorporate the keyword \"{$keyword}\" if not already present. " : '';
        $prompt = "Rewrite the following paragraph to be clearer, more engaging, and SEO-optimised. "
            . $kwInstruction
            . "Tone: {$tone}. Return only the improved paragraph text without explanation.\n\n"
            . "Original:\n{$paragraph}";

        $result = $this->callLlm($prompt, $apiKey, 400);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'original'  => $paragraph,
            'improved'  => $result,
            'ai_generated' => true,
        ]);
    }

    // ── Suggest meta title and description ────────────────────────────────────

    public function suggestMeta(\WP_REST_Request $request): \WP_REST_Response
    {
        $params  = $request->get_json_params();
        $postId  = absint($params['post_id'] ?? 0);
        $content = sanitize_textarea_field($params['content'] ?? '');
        $keyword = sanitize_text_field($params['keyword'] ?? '');

        if ($postId) {
            $content = $content ?: wp_strip_all_tags(get_post_field('post_content', $postId));
            $keyword = $keyword ?: (string) get_post_meta($postId, '_ameverywhere_focus_keyword', true);
        }

        if (empty($content)) {
            return new \WP_Error('missing_content', 'content or post_id is required.', ['status' => 400]);
        }

        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return new \WP_Error('no_api_key', 'No LLM API key configured.', ['status' => 400]);
        }

        $snippet = mb_substr(wp_strip_all_tags($content), 0, 1500);
        $kwNote  = $keyword ? " Focus keyword: \"{$keyword}\"." : '';
        $prompt  = "Based on this content, write an SEO-optimised meta title (max 60 chars) and meta description (max 155 chars).{$kwNote} Return JSON: {\"title\": \"...\", \"description\": \"...\"}\n\n{$snippet}";

        $result = $this->callLlm($prompt, $apiKey, 200);

        if (is_wp_error($result)) {
            return $result;
        }

        preg_match('/\{.*\}/s', $result, $m);
        $parsed = json_decode($m[0] ?? '{}', true);

        return rest_ensure_response([
            'suggested_title'       => sanitize_text_field($parsed['title'] ?? ''),
            'suggested_description' => sanitize_text_field($parsed['description'] ?? ''),
            'ai_generated'          => true,
        ]);
    }

    // ── Prompt templates ──────────────────────────────────────────────────────

    public function getPromptTemplates(\WP_REST_Request $request): \WP_REST_Response
    {
        $templates = [
            'blog_post'    => ['label' => 'Blog Post', 'system' => 'You are an expert blog writer. Write in a friendly, informative tone. Use headers and short paragraphs.'],
            'product_page' => ['label' => 'Product Page', 'system' => 'You are an e-commerce copywriter. Focus on benefits, features, and conversion. Use bullet points.'],
            'landing_page' => ['label' => 'Landing Page', 'system' => 'You are a conversion copywriter. Write persuasively with a clear CTA. Use power words.'],
            'news_article' => ['label' => 'News Article', 'system' => 'You are a journalist. Write in inverted pyramid style. Be factual and concise.'],
            'how_to_guide' => ['label' => 'How-To Guide', 'system' => 'You are a technical writer. Use numbered steps, code examples where needed, and clear explanations.'],
        ];

        return rest_ensure_response($templates);
    }

    // ── LLM helper ────────────────────────────────────────────────────────────

    private function callLlm(string $prompt, string $apiKey, int $maxTokens = 500): string|\WP_Error
    {
        $userId = get_current_user_id();
        $metering = class_exists(\AmEveryWhere\Modules\Ai\UsageMeteringManager::class)
            ? \AmEveryWhere\Modules\Ai\UsageMeteringManager::getInstance()
            : null;

        if ($userId && $metering && !$metering->checkLimit($userId, 'openai')) {
            return new \WP_Error('rate_limit_exceeded', 'AI token usage limit exceeded for this billing cycle.', ['status' => 429]);
        }

        $model    = get_option('ameverywhere_ai_model', get_option('ameverywhere_openai_model', 'gpt-4o-mini'));
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => $model,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => $maxTokens,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            $msg = $body['error']['message'] ?? 'LLM API error.';
            return new \WP_Error('llm_error', $msg, ['status' => 502]);
        }

        $content = (string) ($body['choices'][0]['message']['content'] ?? '');

        if ($userId && $metering) {
            $promptTokens = (int) ceil(strlen($prompt) / 4);
            $outputTokens = (int) ceil(strlen($content) / 4);
            $metering->record($userId, 'openai', 'writing_assistant', $promptTokens + $outputTokens);
        }

        return $content;
    }

    private function getApiKey(): string
    {
        $encryptedKey = get_option('ameverywhere_openai_key', '');
        if (empty($encryptedKey)) {
            $encryptedKey = get_option('ameverywhere_openai_api_key', '');
        }
        return \AmEveryWhere\Core\Security\KeyVault::decrypt($encryptedKey);
    }
}
