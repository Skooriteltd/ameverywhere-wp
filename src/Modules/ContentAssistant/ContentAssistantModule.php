<?php

namespace AmEveryWhere\Modules\ContentAssistant;

if (!defined('ABSPATH')) {
    exit;
}

use AmEveryWhere\Core\Event\EventManager;
use AmEveryWhere\Core\Ai\AiGateway;

class ContentAssistantModule
{
    private EventManager $eventManager;

    public function __construct(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;
    }

    public function boot(): void
    {
        $this->eventManager->addAction('init', [$this, 'registerMetaFields']);
        $this->eventManager->addAction('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
        $this->eventManager->addAction('rest_api_init', [$this, 'registerRestRoutes']);

        // Internal Link Index — keep the link table updated on content changes
        $linkEngine = new InternalLinkEngine();
        $this->eventManager->addAction('save_post', function (int $postId) use ($linkEngine) {
            $post = get_post($postId);
            if ($post) {
                $linkEngine->indexPostLinks($postId, $post);
            }
        }, 20);
        $this->eventManager->addAction('deleted_post', [$linkEngine, 'removePostLinks']);
    }

    public function registerMetaFields(): void
    {
        $metaKeys = [
            '_ameverywhere_meta_title',
            '_ameverywhere_meta_description',
            '_ameverywhere_is_news',
            '_ameverywhere_noindex',
            '_ameverywhere_nofollow',
            '_ameverywhere_canonical_url',
            '_ameverywhere_is_cornerstone',
            '_ameverywhere_og_title',
            '_ameverywhere_og_description',
            '_ameverywhere_og_image',
            '_ameverywhere_twitter_title',
            '_ameverywhere_focus_keyword',
            '_ameverywhere_primary_schema',
            '_ameverywhere_schema_product_name',
            '_ameverywhere_schema_product_description',
            '_ameverywhere_schema_product_price',
            '_ameverywhere_schema_product_currency',
            '_ameverywhere_schema_product_rating',
            '_ameverywhere_schema_product_availability',
            '_ameverywhere_schema_faq_questions',
            '_ameverywhere_schema_howto_name',
            '_ameverywhere_schema_howto_description',
            '_ameverywhere_schema_howto_steps',
            '_ameverywhere_schema_howto_supplies',
            '_ameverywhere_schema_howto_tools',
            '_ameverywhere_schema_localbusiness_name',
            '_ameverywhere_schema_localbusiness_telephone',
            '_ameverywhere_schema_localbusiness_street',
            '_ameverywhere_schema_localbusiness_city',
            '_ameverywhere_schema_localbusiness_postal',
            '_ameverywhere_schema_localbusiness_country',
            // ── Phase 1 Backlog: Unlimited Keywords ──────────────────────
            // JSON array of additional focus keywords beyond the primary one.
            '_ameverywhere_additional_keywords',
            // ── Phase 1 Backlog: Schema Stack (Unlimited Multiple Schemas) ─
            // JSON array of additional schema objects layered on top of primary.
            '_ameverywhere_schema_stack',
            // ── Phase 1 Backlog: Pillar Content ──────────────────────────
            '_ameverywhere_is_pillar',
            // ── Phase 1 Backlog: Per-Post Performance Badges ─────────────
            '_ameverywhere_pagespeed_cache',
            '_ameverywhere_ranking_keywords',
            '_ameverywhere_ranking_keywords_at',
            // ── Social / Misc ─────────────────────────────────────────────
            '_ameverywhere_disable_social_share',
            '_ameverywhere_custom_schema_properties',
            '_ameverywhere_schema_custom_type',
            // Recipe schema fields
            '_ameverywhere_schema_recipe_name',
            '_ameverywhere_schema_recipe_description',
            '_ameverywhere_schema_recipe_ingredients',
            '_ameverywhere_schema_recipe_instructions',
            '_ameverywhere_schema_recipe_prep_time',
            '_ameverywhere_schema_recipe_cook_time',
            '_ameverywhere_schema_recipe_calories',
            '_ameverywhere_schema_recipe_cuisine',
            '_ameverywhere_schema_recipe_yield',
            // Event schema fields
            '_ameverywhere_schema_event_name',
            '_ameverywhere_schema_event_description',
            '_ameverywhere_schema_event_start_date',
            '_ameverywhere_schema_event_end_date',
            '_ameverywhere_schema_event_venue',
            '_ameverywhere_schema_event_address',
            '_ameverywhere_schema_event_organizer',
            '_ameverywhere_schema_event_performer',
            '_ameverywhere_schema_event_price',
            '_ameverywhere_schema_event_currency',
            '_ameverywhere_schema_event_status',
        ];

        foreach (['post', 'page'] as $postType) {
            foreach ($metaKeys as $metaKey) {
                register_post_meta($postType, $metaKey, [
                    'show_in_rest' => true,
                    'single'       => true,
                    'type'         => 'string',
                    'auth_callback' => function() {
                        return current_user_can('edit_posts');
                    }
                ]);
            }
        }
    }

    public function enqueueEditorAssets(): void
    {
        $assetFile = AMEVERYWHERE_PLUGIN_DIR . 'build/editor.asset.php';
        
        if (!file_exists($assetFile)) {
            return;
        }

        $assets = require $assetFile;
        
        $dependencies = array_merge(
            $assets['dependencies'],
            ['wp-plugins', 'wp-edit-post', 'wp-i18n', 'wp-components', 'wp-data', 'wp-core-data']
        );

        wp_enqueue_script(
            'ameverywhere-editor-js',
            AMEVERYWHERE_PLUGIN_URL . 'build/editor.js',
            $dependencies,
            $assets['version'],
            true
        );

        $editorConfig = [
            'apiUrl'            => esc_url_raw(rest_url('ameverywhere/v1')),
            'nonce'             => wp_create_nonce('wp_rest'),
            'defaultShareImage' => esc_url_raw(get_option('ameverywhere_default_share_image', ''))
        ];

        wp_localize_script('ameverywhere-editor-js', 'amEveryWhereEditorConfig', $editorConfig);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('ameverywhere/v1', '/ai/generate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'generateAiContent'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('ameverywhere/v1', '/content/internal-links', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'getContentInternalLinks'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('edit_posts');
    }

    /**
     * Handle AI text generation requests.
     */
    public function generateAiContent(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $action = isset($params['action']) ? sanitize_key($params['action']) : '';
        $title = isset($params['title']) ? sanitize_text_field($params['title']) : '';
        $content = isset($params['content']) ? wp_kses_post($params['content']) : '';
        $focusKeyword = isset($params['focus_keyword']) ? sanitize_text_field($params['focus_keyword']) : '';

        if (empty($action)) {
            return new \WP_Error('missing_action', __('Action parameter is required.', 'ameverywhere'), ['status' => 400]);
        }

        $aiGateway = new AiGateway();
        $systemPrompt = 'You are an elite, senior WordPress SEO and AEO writing specialist. Keep responses professional, highly optimized, and concise.';

        // Build targeted prompt based on desired action
        switch ($action) {
            case 'generate_titles_metas':
                $prompt = "Given the article title: '$title'\nContent: " . wp_strip_all_tags($content) . "\nFocus Keyword: '$focusKeyword'\n\nGenerate exactly 3 high-CTR Meta Titles (under 60 characters) and 3 highly compelling Meta Descriptions (under 160 characters). You MUST format the output as a valid, parsable JSON object with keys 'titles' (array of strings) and 'descriptions' (array of strings). Do not include any explanation or markdown block backticks (like ```json), return raw JSON only.";
                break;

            case 'generate_faqs':
                $prompt = "Based on the content:\n" . wp_strip_all_tags($content) . "\n\nGenerate exactly 3 highly relevant FAQ questions and helpful concise answers. Format your output as a valid, parsable JSON array of objects, where each object has keys 'question' and 'answer'. Do not include markdown code block formatting (like ```json), return raw JSON only.";
                break;

            case 'generate_outline_summary':
                $prompt = "Create a high-level content summary (1-2 sentences) and a detailed, structured SEO outline (using ##, ### Markdown headers) for an article with:\nTitle: '$title'\nFocus Keyword: '$focusKeyword'\n\nProvide structural recommendations (word count target, search intent target, key headings to add). Format your reply nicely using standard Markdown.";
                break;

            case 'writing_assistant':
                $prompt = "Analyze the content for SEO best practices:\nFocus Keyword: '$focusKeyword'\nTitle: '$title'\nContent: " . wp_strip_all_tags($content) . "\n\nProvide exactly 3 bullet points with highly actionable suggestions to improve keyword placement, heading structure, or readability ease. Keep each bullet point short, punchy, and professional.";
                break;

            default:
                return new \WP_Error('invalid_action', __('Invalid AI action.', 'ameverywhere'), ['status' => 400]);
        }

        $result = $aiGateway->queryModel($prompt, $systemPrompt);

        if (!$result['success']) {
            return new \WP_Error('ai_generation_failed', $result['message'], ['status' => 400]);
        }

        $text = $result['text'];

        // Clean up markdown block wrapping if LLM returned it anyway
        if (strpos($text, '```') !== false) {
            $text = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $text);
            $text = trim($text);
        }

        return rest_ensure_response([
            'success' => true,
            'result'  => $text
        ]);
    }

    /**
     * Fetch related link suggestions and orphan status.
     */
    public function getContentInternalLinks(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $postId = isset($params['post_id']) ? intval($params['post_id']) : 0;
        $content = isset($params['content']) ? wp_kses_post($params['content']) : '';

        if (!$postId) {
            return new \WP_Error('missing_id', __('Post ID is required.', 'ameverywhere'), ['status' => 400]);
        }

        $linkEngine = new InternalLinkEngine();
        $recommendations = $linkEngine->getRecommendations($postId, $content);
        $orphanStatus = $linkEngine->checkOrphanStatus($postId);

        return rest_ensure_response([
            'success'         => true,
            'recommendations' => $recommendations,
            'orphan_status'   => $orphanStatus
        ]);
    }
}
