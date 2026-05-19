<?php

namespace RankSavvy\Modules\Sitemap;

class SitemapGenerator
{
    public function serveSitemap(): void
    {
        header('Content-Type: text/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow', true);

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        $posts = get_posts([
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => 1000,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ]);

        foreach ($posts as $post) {
            $noindex = get_post_meta($post->ID, '_ranksavvy_noindex', true);
            if ($noindex === 'yes') {
                continue;
            }

            $url = get_permalink($post->ID);
            $modified = get_post_modified_time('Y-m-d\TH:i:s+00:00', false, $post);

            echo "  <url>\n";
            echo "    <loc>" . esc_url($url) . "</loc>\n";
            echo "    <lastmod>" . esc_html($modified) . "</lastmod>\n";
            
            if (has_post_thumbnail($post->ID)) {
                $imageUrl = get_the_post_thumbnail_url($post->ID, 'full');
                if ($imageUrl) {
                    echo "    <image:image>\n";
                    echo "      <image:loc>" . esc_url($imageUrl) . "</image:loc>\n";
                    echo "    </image:image>\n";
                }
            }
            
            echo "  </url>\n";
        }

        echo '</urlset>';
    }
}
