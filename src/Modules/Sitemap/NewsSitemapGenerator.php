<?php

namespace AmEveryWhere\Modules\Sitemap;

if (!defined('ABSPATH')) {
    exit;
}

class NewsSitemapGenerator
{
    public function serveSitemap(): void
    {
        header('Content-Type: text/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow', true);

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        echo '        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

        // Google News sitemaps only contain URLs published in the last 48 hours
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1000,
            'date_query'     => [
                [
                    'after' => '48 hours ago',
                ],
            ],
        ]);

        $siteName = get_bloginfo('name');

        foreach ($posts as $post) {
            $noindex = get_post_meta($post->ID, '_ameverywhere_noindex', true);
            if ($noindex === 'yes') {
                continue;
            }

            $url = get_permalink($post->ID);
            $publishDate = get_post_time('Y-m-d\TH:i:s+00:00', false, $post);
            $title = get_the_title($post->ID);

            echo "  <url>\n";
            echo "    <loc>" . esc_url($url) . "</loc>\n";
            echo "    <news:news>\n";
            echo "      <news:publication>\n";
            echo "        <news:name>" . esc_html($siteName) . "</news:name>\n";
            echo "        <news:language>" . esc_html(get_bloginfo('language')) . "</news:language>\n";
            echo "      </news:publication>\n";
            echo "      <news:publication_date>" . esc_html($publishDate) . "</news:publication_date>\n";
            echo "      <news:title>" . esc_html($title) . "</news:title>\n";
            echo "    </news:news>\n";
            echo "  </url>\n";
        }

        echo '</urlset>';
    }
}
