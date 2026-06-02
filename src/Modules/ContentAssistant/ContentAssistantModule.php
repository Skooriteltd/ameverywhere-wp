<?php

namespace RankSavvy\Modules\ContentAssistant;

use RankSavvy\Core\Event\EventManager;
use RankSavvy\Core\Ai\AiGateway;

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
            '_ranksavvy_meta_title',
            '_ranksavvy_meta_description',
            '_ranksavvy_is_news',
            '_ranksavvy_noindex',
            '_ranksavvy_nofollow',
            '_ranksavvy_canonical_url',
            '_ranksavvy_is_cornerstone',
            '_ranksavvy_og_title',
            '_ranksavvy_og_description',
            '_ranksavvy_og_image',
            '_ranksavvy_twitter_title',
            '_ranksavvy_focus_keyword',
            '_ranksavvy_primary_schema',
            '_ranksavvy_schema_product_name',
            '_ranksavvy_schema_product_description',
            '_ranksavvy_schema_product_price',
            '_ranksavvy_schema_product_currency',
            '_ranksavvy_schema_product_rating',
            '_ranksavvy_schema_product_availability',
            '_ranksavvy_schema_faq_questions',
            '_ranksavvy_schema_howto_name',
            '_ranksavvy_schema_howto_description',
            '_ranksavvy_schema_howto_steps',
            '_ranksavvy_schema_howto_supplies',
            '_ranksavvy_schema_howto_tools',
            '_ranksavvy_schema_localbusiness_name',
            '_ranksavvy_schema_localbusiness_telephone',
            '_ranksavvy_schema_localbusiness_street',
            '_ranksavvy_schema_localbusiness_city',
            '_ranksavvy_schema_localbusiness_postal',
            '_ranksavvy_schema_localbusiness_country'
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
        $assetFile = RANKSAVVY_PLUGIN_DIR . 'build/editor.asset.php';
        
        if (!file_exists($assetFile)) {
            return;
        }

        $assets = require $assetFile;
        
        $dependencies = array_merge(
            $assets['dependencies'],
            ['wp-plugins', 'wp-edit-post', 'wp-i18n', 'wp-components', 'wp-data', 'wp-core-data']
        );

        wp_enqueue_script(
            'ranksavvy-editor-js',
            RANKSAVVY_PLUGIN_URL . 'build/editor.js',
            $dependencies,
            $assets['version'],
            true
        );

        wp_localize_script('ranksavvy-editor-js', 'rankSavvyEditorConfig', [
            'apiUrl'            => esc_url_raw(rest_url('ranksavvy/v1')),
            'nonce'             => wp_create_nonce('wp_rest'),
            'defaultShareImage' => esc_url_raw(get_option('ranksavvy_default_share_image', ''))
        ]);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('ranksavvy/v1', '/ai/generate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'generateAiContent'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('ranksavvy/v1', '/content/internal-links', [
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
            return new \WP_Error('missing_action', __('Action parameter is required.', 'ranksavvy'), ['status' => 400]);
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
                return new \WP_Error('invalid_action', __('Invalid AI action.', 'ranksavvy'), ['status' => 400]);
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
            return new \WP_Error('missing_id', __('Post ID is required.', 'ranksavvy'), ['status' => 400]);
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
