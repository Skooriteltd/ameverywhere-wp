<?php

namespace RankSavvy\Modules\Seo;

class OpenGraphGenerator
{
    public function outputSocialMetaTags(): void
    {
        if (is_admin() || !is_singular()) {
            return;
        }

        echo "<!-- RankSavvy Social Meta Tags -->\n";
        
        $postId = get_the_ID();
        $title = $this->getOgTitle($postId);
        $description = $this->getOgDescription($postId);
        $url = get_permalink($postId);
        $image = $this->getOgImage($postId);

        // Open Graph
        echo sprintf('<meta property="og:locale" content="%s" />' . "\n", get_locale());
        echo '<meta property="og:type" content="article" />' . "\n";
        echo sprintf('<meta property="og:title" content="%s" />' . "\n", esc_attr($title));
        echo sprintf('<meta property="og:description" content="%s" />' . "\n", esc_attr($description));
        echo sprintf('<meta property="og:url" content="%s" />' . "\n", esc_url($url));
        echo sprintf('<meta property="og:site_name" content="%s" />' . "\n", esc_attr(get_bloginfo('name')));
        
        if ($image) {
            echo sprintf('<meta property="og:image" content="%s" />' . "\n", esc_url($image));
        }

        // Twitter Cards
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo sprintf('<meta name="twitter:title" content="%s" />' . "\n", esc_attr($title));
        echo sprintf('<meta name="twitter:description" content="%s" />' . "\n", esc_attr($description));
        
        if ($image) {
            echo sprintf('<meta name="twitter:image" content="%s" />' . "\n", esc_url($image));
        }

        echo "<!-- /RankSavvy Social Meta Tags -->\n\n";
    }

    private function getOgTitle(int $postId): string
    {
        $customOgTitle = get_post_meta($postId, '_ranksavvy_og_title', true);
        return !empty($customOgTitle) ? $customOgTitle : get_the_title($postId);
    }

    private function getOgDescription(int $postId): string
    {
        $customOgDesc = get_post_meta($postId, '_ranksavvy_og_description', true);
        return !empty($customOgDesc) ? $customOgDesc : wp_trim_words(get_post_field('post_excerpt', $postId), 25);
    }

    private function getOgImage(int $postId): string
    {
        $customOgImage = get_post_meta($postId, '_ranksavvy_og_image', true);
        if (!empty($customOgImage)) {
            return $customOgImage;
        }

        if (has_post_thumbnail($postId)) {
            $thumbnailUrl = get_the_post_thumbnail_url($postId, 'full');
            if ($thumbnailUrl !== false) {
                return $thumbnailUrl;
            }
        }

        // Fallback to site default image or empty
        return '';
    }
}
