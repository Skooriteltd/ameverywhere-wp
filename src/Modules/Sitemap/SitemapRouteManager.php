<?php

namespace RankSavvy\Modules\Sitemap;

/**
 * Manages rewrite rules and virtual endpoint interception for XML sitemaps and IndexNow keys.
 *
 * Supported routes:
 *   /sitemap.xml                   → Sitemap Index
 *   /sitemap-posts-YYYY-MM.xml     → Date-based sub-sitemap chunk
 *   /sitemap-news.xml              → Google News sitemap (last 48 hours)
 *   /news-sitemap.xml              → Legacy alias for news sitemap
 *   /video-sitemap.xml             → Video sitemap
 *   /{indexnow-key}.txt            → IndexNow verification file
 */
class SitemapRouteManager
{
    private SitemapGenerator $sitemapGenerator;
    private NewsSitemapGenerator $newsSitemapGenerator;
    private VideoSitemapGenerator $videoSitemapGenerator;

    public function __construct(
        SitemapGenerator $sitemapGenerator,
        NewsSitemapGenerator $newsSitemapGenerator,
        VideoSitemapGenerator $videoSitemapGenerator
    ) {
        $this->sitemapGenerator = $sitemapGenerator;
        $this->newsSitemapGenerator = $newsSitemapGenerator;
        $this->videoSitemapGenerator = $videoSitemapGenerator;
    }

    public function addRewriteRules(): void
    {
        if (SitemapSettings::get('enable_index_sitemap', 'yes') === 'yes') {
            // Main sitemap index
            add_rewrite_rule('^sitemap\.xml$', 'index.php?ranksavvy_sitemap=index', 'top');

            // Date-based sub-sitemap chunks: sitemap-posts-2024-05.xml
            add_rewrite_rule(
                '^sitemap-posts-(\d{4}-\d{2})\.xml$',
                'index.php?ranksavvy_sitemap=chunk&ranksavvy_sitemap_period=$matches[1]',
                'top'
            );
        }

        if (SitemapSettings::get('enable_news_sitemap', 'no') === 'yes') {
            // Primary news sitemap route
            add_rewrite_rule('^sitemap-news\.xml$', 'index.php?ranksavvy_sitemap=news', 'top');
            // Legacy alias for backward compatibility
            add_rewrite_rule('^news-sitemap\.xml$', 'index.php?ranksavvy_sitemap=news', 'top');
        }

        if (SitemapSettings::get('enable_video_sitemap', 'no') === 'yes') {
            add_rewrite_rule('^video-sitemap\.xml$', 'index.php?ranksavvy_sitemap=video', 'top');
        }

        $bingKey = get_option('ranksavvy_indexnow_key');
        if (!empty($bingKey)) {
            add_rewrite_rule('^' . preg_quote($bingKey) . '\.txt$', 'index.php?ranksavvy_bing_key=' . $bingKey, 'top');
        }
    }

    /**
     * Register custom query vars so WordPress passes them through.
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'ranksavvy_sitemap';
        $vars[] = 'ranksavvy_sitemap_period';
        $vars[] = 'ranksavvy_bing_key';
        return $vars;
    }

    public function handleSitemapRequests(): void
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
        // Strip query strings for clean matching
        $path = strtok($uri, '?');

        // ── News Sitemap (sitemap-news.xml or legacy news-sitemap.xml) ──
        if (preg_match('#/(sitemap-news|news-sitemap)\.xml$#', $path)) {
            if (SitemapSettings::get('enable_news_sitemap', 'no') === 'yes') {
                $this->newsSitemapGenerator->serveSitemap();
                exit;
            } else {
                $this->trigger404();
            }
        }

        // ── Video Sitemap ──
        if (preg_match('#/video-sitemap\.xml$#', $path)) {
            if (SitemapSettings::get('enable_video_sitemap', 'no') === 'yes') {
                $this->videoSitemapGenerator->serveSitemap();
                exit;
            } else {
                $this->trigger404();
            }
        }

        // ── Date-based sub-sitemap chunk (sitemap-posts-2024-05.xml) ──
        if (preg_match('#/sitemap-posts-(\d{4}-\d{2})\.xml$#', $path, $matches)) {
            if (SitemapSettings::get('enable_index_sitemap', 'yes') === 'yes') {
                $this->sitemapGenerator->serveSitemap($matches[1]);
                exit;
            } else {
                $this->trigger404();
            }
        }

        // ── Main Sitemap Index ──
        if (preg_match('#/sitemap\.xml$#', $path)) {
            if (SitemapSettings::get('enable_index_sitemap', 'yes') === 'yes') {
                $this->sitemapGenerator->serveSitemap(null);
                exit;
            } else {
                $this->trigger404();
            }
        }

        // ── IndexNow verification file ──
        $bingKey = get_option('ranksavvy_indexnow_key');
        if (!empty($bingKey) && strpos($path, '/' . $bingKey . '.txt') !== false) {
            header('Content-Type: text/plain; charset=utf-8');
            echo esc_html($bingKey);
            exit;
        }
    }

    private function trigger404(): void
    {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        get_template_part('404');
        exit;
    }

    public function flushRules(): void
    {
        $this->addRewriteRules();
        flush_rewrite_rules();
    }
}
