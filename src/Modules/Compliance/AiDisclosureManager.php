<?php

namespace AmEveryWhere\Modules\Compliance;

/**
 * AiDisclosureManager
 *
 * Injects FTC-compliant AI content disclosure notices on posts flagged with
 * `_ameverywhere_ai_generated = 'yes'`. Also adds a visible indicator column
 * in the WordPress post list table.
 *
 * BL-016
 */
class AiDisclosureManager
{
    private const AI_META_KEY       = '_ameverywhere_ai_generated';
    private const DISCLOSURE_TEXT   = 'ameverywhere_ai_disclosure_text';
    private const DISCLOSURE_POS    = 'ameverywhere_ai_disclosure_position';

    private const DEFAULT_TEXT = 'This content was created with AI assistance and reviewed by our editorial team.';

    public function register(): void
    {
        add_filter('the_content', [$this, 'injectDisclosure']);
        add_filter('manage_posts_columns', [$this, 'addPostColumn']);
        add_filter('manage_pages_columns', [$this, 'addPostColumn']);
        add_action('manage_posts_custom_column', [$this, 'outputPostColumn'], 10, 2);
        add_action('manage_pages_custom_column', [$this, 'outputPostColumn'], 10, 2);
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    // ── Content filter ────────────────────────────────────────────────────────

    public function injectDisclosure(string $content): string
    {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $postId = get_the_ID();
        if (!$postId) {
            return $content;
        }

        $isAi = get_post_meta($postId, self::AI_META_KEY, true);
        if ($isAi !== 'yes') {
            return $content;
        }

        $text     = get_option(self::DISCLOSURE_TEXT, self::DEFAULT_TEXT);
        $position = get_option(self::DISCLOSURE_POS, 'after');

        $notice = sprintf(
            '<aside class="ameverywhere-ai-disclosure" aria-label="%s" role="note" style="padding:12px 16px;margin:16px 0;border-left:4px solid #4f46e5;background:#f5f3ff;font-size:0.875em;">'
            . '<p style="margin:0;">🤖 %s</p>'
            . '</aside>',
            esc_attr__('AI Content Disclosure', 'ameverywhere'),
            esc_html($text)
        );

        return ($position === 'before')
            ? $notice . $content
            : $content . $notice;
    }

    // ── Post list column ──────────────────────────────────────────────────────

    public function addPostColumn(array $columns): array
    {
        $columns['ameverywhere_ai'] = '🤖 AI';
        return $columns;
    }

    public function outputPostColumn(string $column, int $postId): void
    {
        if ($column !== 'ameverywhere_ai') {
            return;
        }

        $isAi = get_post_meta($postId, self::AI_META_KEY, true);
        if ($isAi === 'yes') {
            echo '<span title="AI-assisted content" style="font-size:1.2em;">🤖</span>';
        } else {
            echo '<span style="color:#ccc;">—</span>';
        }
    }

    // ── REST routes ───────────────────────────────────────────────────────────

    public function registerRoutes(): void
    {
        register_rest_route('ameverywhere/v1', '/settings/ai-disclosure', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSettings'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'saveSettings'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
        ]);
    }

    public function getSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'text'     => get_option(self::DISCLOSURE_TEXT, self::DEFAULT_TEXT),
            'position' => get_option(self::DISCLOSURE_POS, 'after'),
        ]);
    }

    public function saveSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        if (isset($params['text'])) {
            update_option(self::DISCLOSURE_TEXT, sanitize_text_field($params['text']));
        }

        if (isset($params['position'])) {
            $position = in_array($params['position'], ['before', 'after'], true) ? $params['position'] : 'after';
            update_option(self::DISCLOSURE_POS, $position);
        }

        return rest_ensure_response(['success' => true]);
    }
}
