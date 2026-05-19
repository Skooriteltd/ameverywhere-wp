<?php

namespace RankSavvy\Modules\Sitemap;

/**
 * Generates and caches the main XML sitemap with dynamic exclusions.
 * Caches sitemap output using WordPress Transients for extreme performance.
 */
class SitemapGenerator
{
    private const CACHE_TRANSIENT = 'ranksavvy_sitemap_xml_cache';
    private const CACHE_EXPIRATION = 4 * HOUR_IN_SECONDS;

    /**
     * Serves the main sitemap XML.
     */
    public function serveSitemap(): void
    {
        header('Content-Type: text/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow', true);

        // Try to fetch cached XML
        $xml = get_transient(self::CACHE_TRANSIENT);
        if ($xml === false) {
            $xml = $this->generateXml();
            set_transient(self::CACHE_TRANSIENT, $xml, self::CACHE_EXPIRATION);
        }

        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Clear sitemap cache (call when posts are updated or settings change).
     */
    public static function clearCache(): void
    {
        delete_transient(self::CACHE_TRANSIENT);
    }

    /**
     * Generate the sitemap XML string.
     */
    private function generateXml(): string
    {
        $excludePostTypes = get_option('ranksavvy_sitemap_exclude_types', []);
        $excludePostsString = get_option('ranksavvy_sitemap_exclude_posts', '');
        
        $excludePostIds = [];
        if (!empty($excludePostsString)) {
            $excludePostIds = array_map('intval', array_filter(array_map('trim', explode(',', $excludePostsString))));
        }

        // Get active public post types
        $postTypes = get_post_types(['public' => true]);
        
        // Remove excluded post types
        if (is_array($excludePostTypes)) {
            $postTypes = array_diff($postTypes, $excludePostTypes);
        }

        if (empty($postTypes)) {
            $postTypes = ['post', 'page'];
        }

        $queryArgs = [
            'post_type'      => array_values($postTypes),
            'post_status'    => 'publish',
            'posts_per_page' => 1000,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ];

        if (!empty($excludePostIds)) {
            $queryArgs['post__not_in'] = $excludePostIds;
        }

        $posts = get_posts($queryArgs);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        // Always add homepage first if it's not excluded
        if (!in_array(get_option('page_on_front'), $excludePostIds, true)) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . esc_url(home_url('/')) . "</loc>\n";
            $xml .= "    <lastmod>" . esc_html(current_time('c')) . "</lastmod>\n";
            $xml .= "    <changefreq>daily</changefreq>\n";
            $xml .= "    <priority>1.0</priority>\n";
            $xml .= "  </url>\n";
        }

        foreach ($posts as $post) {
            // Respect the page_on_front duplication
            if ($post->ID === (int) get_option('page_on_front')) {
                continue;
            }

            // Exclude posts marked as noindex manually
            $noindex = get_post_meta($post->ID, '_ranksavvy_noindex', true);
            if ($noindex === 'yes') {
                continue;
            }

            $url = get_permalink($post->ID);
            $modified = get_post_modified_time('c', false, $post);

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . esc_url($url) . "</loc>\n";
            $xml .= "    <lastmod>" . esc_html($modified) . "</lastmod>\n";
            
            // Priority based on post type
            $priority = ($post->post_type === 'page') ? '0.8' : '0.6';
            $xml .= "    <priority>" . $priority . "</priority>\n";
            
            if (has_post_thumbnail($post->ID)) {
                $imageUrl = get_the_post_thumbnail_url($post->ID, 'full');
                if ($imageUrl) {
                    $xml .= "    <image:image>\n";
                    $xml .= "      <image:loc>" . esc_url($imageUrl) . "</image:loc>\n";
                    $xml .= "    </image:image>\n";
                }
            }
            
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }
}
