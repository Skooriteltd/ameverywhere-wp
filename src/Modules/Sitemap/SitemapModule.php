<?php

namespace AmEveryWhere\Modules\Sitemap;

use AmEveryWhere\Core\Event\EventManager;

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

        // Register custom query vars for date-based sub-sitemap routing
        $this->eventManager->addFilter('query_vars', [$this->routeManager, 'registerQueryVars']);
        
        // Handle virtual sitemap requests
        $this->eventManager->addAction('template_redirect', [$this->routeManager, 'handleSitemapRequests'], 0);
        
        // Ensure rewrite rules are flushed on activation
        $this->eventManager->addAction('ameverywhere_activation', [$this->routeManager, 'flushRules']);

        // Clear sitemap cache when content changes
        $this->eventManager->addAction('save_post', [SitemapGenerator::class, 'clearCache']);
        $this->eventManager->addAction('deleted_post', [SitemapGenerator::class, 'clearCache']);
        $this->eventManager->addAction('transition_post_status', [SitemapGenerator::class, 'clearCache']);

        $this->eventManager->addAction('save_post', [VideoSitemapGenerator::class, 'clearCache']);
        $this->eventManager->addAction('deleted_post', [VideoSitemapGenerator::class, 'clearCache']);
        $this->eventManager->addAction('transition_post_status', [VideoSitemapGenerator::class, 'clearCache']);

        // ── Phase 1 Backlog #10: Automatically notify search engines when sitemap changes ──
        // Fires on every publish/update — pings Google and Bing with the sitemap index URL
        // using a debounced transient (max one ping per site per 60 minutes) to avoid abuse.
        $this->eventManager->addAction('transition_post_status', [$this, 'maybePingSearchEngines'], 20, 3);

        // Register configuration routes
        $this->eventManager->addAction('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Ping Google and Bing with the sitemap index URL when a post transitions
     * to/from "publish". Debounced to at most once per 60 minutes site-wide.
     */
    public function maybePingSearchEngines(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        // Only fire when content becomes or updates to published status
        if ($newStatus !== 'publish') {
            return;
        }

        // Skip auto-drafts, revisions, and non-public post types
        if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) {
            return;
        }

        // Debounce: only ping once per 60 minutes to avoid hammering the APIs
        $throttleKey = 'ameverywhere_sitemap_ping_throttle';
        if (get_transient($throttleKey)) {
            return;
        }
        set_transient($throttleKey, true, HOUR_IN_SECONDS);

        $sitemapUrl = home_url('/sitemap.xml');

        // Ping Google (public URL — no auth required)
        wp_remote_get(
            'https://www.google.com/ping?sitemap=' . urlencode($sitemapUrl),
            ['timeout' => 5, 'blocking' => false]
        );

        // Ping Bing via IndexNow if a key is configured
        $indexNowKey = get_option('ameverywhere_indexnow_key', '');
        if (!empty($indexNowKey)) {
            $bingApi = new \AmEveryWhere\Modules\Indexing\BingIndexNowApi();
            $bingApi->ping(get_permalink($post->ID), $indexNowKey);
        }

    }

    /**
     * Register REST API routes for sitemap settings.
     */
    public function registerRoutes(): void
    {
        register_rest_route('ameverywhere/v1', '/settings/sitemaps', [
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
            'enable_index_sitemap' => SitemapSettings::get('enable_index_sitemap', 'yes') === 'yes',
            'enable_news_sitemap'  => SitemapSettings::get('enable_news_sitemap', 'no') === 'yes',
            'enable_video_sitemap' => SitemapSettings::get('enable_video_sitemap', 'no') === 'yes',
            'exclude_types'        => SitemapSettings::get('sitemap_exclude_types', []),
            'exclude_posts'        => SitemapSettings::get('sitemap_exclude_posts', ''),
            'sitemap_changefreq_post' => SitemapSettings::get('sitemap_changefreq_post', 'weekly'),
            'sitemap_changefreq_page' => SitemapSettings::get('sitemap_changefreq_page', 'weekly'),
            'sitemap_priority_post'   => SitemapSettings::get('sitemap_priority_post', '0.6'),
            'sitemap_priority_page'   => SitemapSettings::get('sitemap_priority_page', '0.8'),
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
            SitemapSettings::set('enable_index_sitemap', $params['enable_index_sitemap'] ? 'yes' : 'no');
        }

        if (isset($params['enable_news_sitemap'])) {
            SitemapSettings::set('enable_news_sitemap', $params['enable_news_sitemap'] ? 'yes' : 'no');
        }

        if (isset($params['enable_video_sitemap'])) {
            SitemapSettings::set('enable_video_sitemap', $params['enable_video_sitemap'] ? 'yes' : 'no');
        }

        if (isset($params['exclude_types']) && is_array($params['exclude_types'])) {
            $cleaned = array_map('sanitize_text_field', $params['exclude_types']);
            SitemapSettings::set('sitemap_exclude_types', $cleaned);
        }

        if (isset($params['exclude_posts'])) {
            SitemapSettings::set('sitemap_exclude_posts', sanitize_text_field($params['exclude_posts']));
        }

        if (isset($params['sitemap_changefreq_post'])) {
            SitemapSettings::set('sitemap_changefreq_post', sanitize_text_field($params['sitemap_changefreq_post']));
        }

        if (isset($params['sitemap_changefreq_page'])) {
            SitemapSettings::set('sitemap_changefreq_page', sanitize_text_field($params['sitemap_changefreq_page']));
        }

        if (isset($params['sitemap_priority_post'])) {
            SitemapSettings::set('sitemap_priority_post', sanitize_text_field($params['sitemap_priority_post']));
        }

        if (isset($params['sitemap_priority_page'])) {
            SitemapSettings::set('sitemap_priority_page', sanitize_text_field($params['sitemap_priority_page']));
        }

        // Clear transient cache so new rules apply immediately
        SitemapGenerator::clearCache();
        VideoSitemapGenerator::clearCache();

        return rest_ensure_response(['success' => true]);
    }
}
