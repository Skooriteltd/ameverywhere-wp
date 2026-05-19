<?php

namespace RankSavvy\Modules\Sitemap;

/**
 * Manages rewrite rules and virtual endpoint interception for XML sitemaps and IndexNow keys.
 * Respects toggle settings for enabling/disabling sitemaps.
 */
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
        if (get_option('ranksavvy_enable_index_sitemap', 'yes') === 'yes') {
            add_rewrite_rule('^sitemap\.xml$', 'index.php?ranksavvy_sitemap=index', 'top');
        }
        
        if (get_option('ranksavvy_enable_news_sitemap', 'no') === 'yes') {
            add_rewrite_rule('^news-sitemap\.xml$', 'index.php?ranksavvy_sitemap=news', 'top');
        }
        
        $bingKey = get_option('ranksavvy_indexnow_key');
        if (!empty($bingKey)) {
            add_rewrite_rule('^' . preg_quote($bingKey) . '\.txt$', 'index.php?ranksavvy_bing_key=' . $bingKey, 'top');
        }
    }

    public function handleSitemapRequests(): void
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));

        // Check if News Sitemap is requested and enabled
        if (strpos($uri, '/news-sitemap.xml') !== false) {
            if (get_option('ranksavvy_enable_news_sitemap', 'no') === 'yes') {
                $this->newsSitemapGenerator->serveSitemap();
                exit;
            } else {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part('404');
                exit;
            }
        }

        // Check if Main Sitemap is requested and enabled
        if (strpos($uri, '/sitemap.xml') !== false) {
            if (get_option('ranksavvy_enable_index_sitemap', 'yes') === 'yes') {
                $this->sitemapGenerator->serveSitemap();
                exit;
            } else {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part('404');
                exit;
            }
        }

        // Check for IndexNow confirmation text file
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
