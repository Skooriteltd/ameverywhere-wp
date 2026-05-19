<?php

namespace RankSavvy\Modules\Sitemap;

use RankSavvy\Core\Event\EventManager;

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
    }
}
