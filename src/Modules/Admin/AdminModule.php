<?php

namespace RankSavvy\Modules\Admin;

use RankSavvy\Core\Event\EventManager;
use RankSavvy\Core\Security\KeyVault;

class AdminModule
{
    private EventManager $eventManager;
    private AdminMenu $adminMenu;

    public function __construct(EventManager $eventManager, AdminMenu $adminMenu)
    {
        $this->eventManager = $eventManager;
        $this->adminMenu = $adminMenu;
    }

    public function boot(): void
    {
        $this->eventManager->addAction('admin_menu', [$this->adminMenu, 'registerMenu']);
        $this->eventManager->addAction('admin_enqueue_scripts', [$this->adminMenu, 'enqueueAssets']);
        $this->eventManager->addAction('rest_api_init', [$this, 'registerRestRoutes']);

        // Register SEO Score column in Posts/Pages list tables
        $seoScoreColumn = new SeoScoreColumn();
        $seoScoreColumn->register();
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('ranksavvy/v1', '/settings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'updateSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route('ranksavvy/v1', '/settings/ai/test-connection', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'testAiConnection'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('ranksavvy/v1', '/index-url', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'manualIndexUrl'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('ranksavvy/v1', '/indexing/status', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getGoogleIndexingStatus'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $aiGateway = new \RankSavvy\Core\Ai\AiGateway();
        
        $openaiKeyStored = get_option('ranksavvy_openai_key', '');
        $openaiKey = !empty($openaiKeyStored) ? '••••••••••••' : '';

        $anthropicKeyStored = get_option('ranksavvy_anthropic_key', '');
        $anthropicKey = !empty($anthropicKeyStored) ? '••••••••••••' : '';

        return rest_ensure_response([
            'google_indexing_key' => get_option('ranksavvy_google_indexing_key', ''),
            'indexnow_key'        => get_option('ranksavvy_indexnow_key', ''),
            'auto_index'          => get_option('ranksavvy_auto_index', 'yes') === 'yes',
            'google_verify'       => get_option('ranksavvy_google_verify', ''),
            'bing_verify'         => get_option('ranksavvy_bing_verify', ''),
            'yandex_verify'       => get_option('ranksavvy_yandex_verify', ''),
            'pinterest_verify'    => get_option('ranksavvy_pinterest_verify', ''),
            'rss_before_content'  => get_option('ranksavvy_rss_before_content', ''),
            'rss_after_content'   => get_option('ranksavvy_rss_after_content', ''),
            'breadcrumb_separator'   => get_option('ranksavvy_breadcrumb_separator', '›'),
            'breadcrumb_home_label'  => get_option('ranksavvy_breadcrumb_home_label', 'Home'),
            'breadcrumb_auto_insert' => get_option('ranksavvy_breadcrumb_auto_insert', 'none'),
            'sitemap_exclude_post_types' => get_option('ranksavvy_sitemap_exclude_post_types', ''),
            'sitemap_exclude_posts'      => get_option('ranksavvy_sitemap_exclude_posts', ''),
            
            // Secure AI Vault settings
            'ai_provider'         => get_option('ranksavvy_ai_provider', 'openai'),
            'openai_key'          => $openaiKey,
            'anthropic_key'       => $anthropicKey,
            'ollama_url'          => get_option('ranksavvy_ollama_url', 'http://localhost:11434'),
            'default_share_image' => get_option('ranksavvy_default_share_image', '')
        ]);
    }

    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $aiGateway = new \RankSavvy\Core\Ai\AiGateway();

        if (isset($params['google_indexing_key'])) {
            $jsonValue = wp_unslash($params['google_indexing_key']);
            if (empty($jsonValue) || json_decode($jsonValue) !== null) {
                update_option('ranksavvy_google_indexing_key', $jsonValue);
            }
        }

        if (isset($params['indexnow_key'])) {
            update_option('ranksavvy_indexnow_key', sanitize_text_field($params['indexnow_key']));
        }

        if (isset($params['auto_index'])) {
            update_option('ranksavvy_auto_index', $params['auto_index'] ? 'yes' : 'no');
        }

        if (isset($params['google_verify'])) {
            update_option('ranksavvy_google_verify', sanitize_text_field($params['google_verify']));
        }

        if (isset($params['bing_verify'])) {
            update_option('ranksavvy_bing_verify', sanitize_text_field($params['bing_verify']));
        }

        if (isset($params['yandex_verify'])) {
            update_option('ranksavvy_yandex_verify', sanitize_text_field($params['yandex_verify']));
        }

        if (isset($params['pinterest_verify'])) {
            update_option('ranksavvy_pinterest_verify', sanitize_text_field($params['pinterest_verify']));
        }

        if (isset($params['rss_before_content'])) {
            update_option('ranksavvy_rss_before_content', wp_kses_post($params['rss_before_content']));
        }

        if (isset($params['rss_after_content'])) {
            update_option('ranksavvy_rss_after_content', wp_kses_post($params['rss_after_content']));
        }

        if (isset($params['breadcrumb_separator'])) {
            update_option('ranksavvy_breadcrumb_separator', sanitize_text_field($params['breadcrumb_separator']));
        }

        if (isset($params['breadcrumb_home_label'])) {
            update_option('ranksavvy_breadcrumb_home_label', sanitize_text_field($params['breadcrumb_home_label']));
        }

        if (isset($params['breadcrumb_auto_insert'])) {
            update_option('ranksavvy_breadcrumb_auto_insert', sanitize_text_field($params['breadcrumb_auto_insert']));
        }

        if (isset($params['sitemap_exclude_post_types'])) {
            update_option('ranksavvy_sitemap_exclude_post_types', sanitize_text_field($params['sitemap_exclude_post_types']));
        }

        if (isset($params['sitemap_exclude_posts'])) {
            update_option('ranksavvy_sitemap_exclude_posts', sanitize_text_field($params['sitemap_exclude_posts']));
        }

        // Save secure AI parameters
        if (isset($params['ai_provider'])) {
            update_option('ranksavvy_ai_provider', sanitize_text_field($params['ai_provider']));
        }

        if (isset($params['openai_key'])) {
            $val = $params['openai_key'];
            if ($val !== '••••••••••••') {
                update_option('ranksavvy_openai_key', empty($val) ? '' : KeyVault::encrypt($val));
            }
        }

        if (isset($params['anthropic_key'])) {
            $val = $params['anthropic_key'];
            if ($val !== '••••••••••••') {
                update_option('ranksavvy_anthropic_key', empty($val) ? '' : KeyVault::encrypt($val));
            }
        }

        if (isset($params['ollama_url'])) {
            update_option('ranksavvy_ollama_url', esc_url_raw($params['ollama_url']));
        }

        if (isset($params['default_share_image'])) {
            update_option('ranksavvy_default_share_image', esc_url_raw($params['default_share_image']));
        }

        return rest_ensure_response(['success' => true]);
    }

    /**
     * Authenticated endpoint to test AI provider connections.
     */
    public function testAiConnection(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        
        $provider = isset($params['ai_provider']) ? sanitize_text_field($params['ai_provider']) : 'openai';
        $aiGateway = new \RankSavvy\Core\Ai\AiGateway();

        // Temporarily override parameters for connection test if updated in the UI
        if (isset($params['openai_key']) && $params['openai_key'] !== '••••••••••••') {
            update_option('ranksavvy_openai_key', empty($params['openai_key']) ? '' : KeyVault::encrypt($params['openai_key']));
        }
        if (isset($params['anthropic_key']) && $params['anthropic_key'] !== '••••••••••••') {
            update_option('ranksavvy_anthropic_key', empty($params['anthropic_key']) ? '' : KeyVault::encrypt($params['anthropic_key']));
        }
        if (isset($params['ollama_url'])) {
            update_option('ranksavvy_ollama_url', esc_url_raw($params['ollama_url']));
        }
        update_option('ranksavvy_ai_provider', $provider);

        // Ping the provider with a short prompt
        $result = $aiGateway->queryModel('Return exactly the word "SUCCESS" and nothing else.', 'System test.');

        if ($result['success'] && stripos($result['text'], 'SUCCESS') !== false) {
            return rest_ensure_response([
                'success' => true,
                'message' => __('Connection test passed successfully!', 'ranksavvy')
            ]);
        }

        $msg = isset($result['message']) ? $result['message'] : __('Failed to connect to the selected AI provider.', 'ranksavvy');
        return new \WP_Error('ai_conn_fail', $msg, ['status' => 400]);
    }

    public function manualIndexUrl(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = $request->get_param('post_id');
        if (!$postId) {
            return new \WP_Error('missing_param', 'Post ID is required.', ['status' => 400]);
        }
        
        $url = get_permalink($postId);
        if (!$url) {
            return new \WP_Error('invalid_post', 'Invalid Post ID.', ['status' => 400]);
        }

        // Fire job in the background securely via WP Cron
        $queueManager = new \RankSavvy\Core\Queue\QueueManager();
        $queueManager->push(\RankSavvy\Modules\Indexing\IndexingJob::class, [
            'post_id' => $postId,
            'url'     => $url,
            'action'  => 'URL_UPDATED'
        ]);

        return rest_ensure_response(['success' => true, 'message' => 'Submitted to search engines successfully.']);
    }

    public function getGoogleIndexingStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $url = $request->get_param('url');
        if (empty($url)) {
            return new \WP_Error('missing_url', 'URL is required.', ['status' => 400]);
        }

        $api = new \RankSavvy\Modules\Indexing\GoogleIndexingApi();
        $result = $api->checkStatus($url);

        return rest_ensure_response($result);
    }
}

