<?php

namespace RankSavvy\Modules\Admin;

use RankSavvy\Core\Event\EventManager;

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

        register_rest_route('ranksavvy/v1', '/index-url', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'manualIndexUrl'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'google_indexing_key' => get_option('ranksavvy_google_indexing_key', ''),
            'indexnow_key'        => get_option('ranksavvy_indexnow_key', ''),
            'auto_index'          => get_option('ranksavvy_auto_index', 'yes') === 'yes',
        ]);
    }

    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        if (isset($params['google_indexing_key'])) {
            $jsonValue = wp_unslash($params['google_indexing_key']);
            // Validate that it's actually valid JSON before saving
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

        return rest_ensure_response(['success' => true]);
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
}
