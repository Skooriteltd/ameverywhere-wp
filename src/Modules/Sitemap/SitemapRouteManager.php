<?php

namespace RankSavvy\Modules\Sitemap;

class SitemapRouteManager
{
    private SitemapGenerator $sitemapGenerator;
    private NewsSitemapGenerator $newsSitemapGenerator;

    public function __construct(SitemapGenerator $sitemapGenerator, NewsSitemapGenerator $newsSitemapGenerator)
    {
        $this->sitemapGenerator = $sitemapGenerator;
        $this->newsSitemapGenerator = $newsSitemapGenerator;
    }

    public function addRewriteRules(): void
    {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?ranksavvy_sitemap=index', 'top');
        add_rewrite_rule('^news-sitemap\.xml$', 'index.php?ranksavvy_sitemap=news', 'top');
        
        $bingKey = get_option('ranksavvy_indexnow_key');
        if (!empty($bingKey)) {
            add_rewrite_rule('^' . preg_quote($bingKey) . '\.txt$', 'index.php?ranksavvy_bing_key=' . $bingKey, 'top');
        }
    }

    public function handleSitemapRequests(): void
    {
        global $wp_query;

        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));

        if (strpos($uri, '/news-sitemap.xml') !== false) {
            $this->newsSitemapGenerator->serveSitemap();
            exit;
        }

        if (strpos($uri, '/sitemap.xml') !== false) {
            $this->sitemapGenerator->serveSitemap();
            exit;
        }

        $bingKey = get_option('ranksavvy_indexnow_key');
        if (!empty($bingKey) && strpos($uri, '/' . $bingKey . '.txt') !== false) {
            header('Content-Type: text/plain; charset=utf-8');
            echo esc_html($bingKey);
            exit;
        }
    }

    public function flushRules(): void
    {
        $this->addRewriteRules();
        flush_rewrite_rules();
    }
}
