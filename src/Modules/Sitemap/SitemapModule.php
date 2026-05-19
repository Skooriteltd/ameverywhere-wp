<?php

namespace RankSavvy\Modules\Sitemap;

use RankSavvy\Core\Event\EventManager;

/**
 * Boots the sitemap module, registers REST endpoints for sitemaps config,
 * and attaches hooks to clear sitemap cache on post mutations.
 */
class SitemapModule
{
    private EventManager $eventManager;
    private SitemapRouteManager $routeManager;

    public function __construct(EventManager $eventManager, SitemapRouteManager $routeManager)
    {
        $this->eventManager = $eventManager;
        $this->routeManager = $routeManager;
    }

    public function boot(): void
    {
        // Disable WordPress Core Sitemaps
        $this->eventManager->addFilter('wp_sitemaps_enabled', '__return_false');

        // Add custom rewrite rules
        $this->eventManager->addAction('init', [$this->routeManager, 'addRewriteRules']);
        
        // Handle virtual sitemap requests
        $this->eventManager->addAction('template_redirect', [$this->routeManager, 'handleSitemapRequests'], 0);
        
        // Ensure rewrite rules are flushed on activation
        $this->eventManager->addAction('ranksavvy_activation', [$this->routeManager, 'flushRules']);

        // Clear sitemap cache when content changes
        $this->eventManager->addAction('save_post', [SitemapGenerator::class, 'clearCache']);
        $this->eventManager->addAction('deleted_post', [SitemapGenerator::class, 'clearCache']);
        $this->eventManager->addAction('transition_post_status', [SitemapGenerator::class, 'clearCache']);

        // Register configuration routes
        $this->eventManager->addAction('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST API routes for sitemap settings.
     */
    public function registerRoutes(): void
    {
        register_rest_route('ranksavvy/v1', '/settings/sitemaps', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSitemapSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'updateSitemapSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Get sitemap configuration.
     */
    public function getSitemapSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        // Get all public post types for checkboxes in frontend
        $allPublicTypes = get_post_types(['public' => true], 'objects');
        $typesList = [];
        foreach ($allPublicTypes as $type) {
            $typesList[] = [
                'name'  => $type->name,
                'label' => $type->label ?: $type->name,
            ];
        }

        return rest_ensure_response([
            'enable_index_sitemap' => get_option('ranksavvy_enable_index_sitemap', 'yes') === 'yes',
            'enable_news_sitemap'  => get_option('ranksavvy_enable_news_sitemap', 'no') === 'yes',
            'exclude_types'        => get_option('ranksavvy_sitemap_exclude_types', []),
            'exclude_posts'        => get_option('ranksavvy_sitemap_exclude_posts', ''),
            'available_types'      => $typesList,
        ]);
    }

    /**
     * Update sitemap configuration.
     */
    public function updateSitemapSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        if (isset($params['enable_index_sitemap'])) {
            update_option('ranksavvy_enable_index_sitemap', $params['enable_index_sitemap'] ? 'yes' : 'no');
        }

        if (isset($params['enable_news_sitemap'])) {
            update_option('ranksavvy_enable_news_sitemap', $params['enable_news_sitemap'] ? 'yes' : 'no');
        }

        if (isset($params['exclude_types']) && is_array($params['exclude_types'])) {
            $cleaned = array_map('sanitize_text_field', $params['exclude_types']);
            update_option('ranksavvy_sitemap_exclude_types', $cleaned);
        }

        if (isset($params['exclude_posts'])) {
            update_option('ranksavvy_sitemap_exclude_posts', sanitize_text_field($params['exclude_posts']));
        }

        // Clear transient cache so new rules apply immediately
        SitemapGenerator::clearCache();

        return rest_ensure_response(['success' => true]);
    }
}
