<?php

namespace RankSavvy\Modules\Seo;

class MetaTagsGenerator
{
    public function outputStandardMetaTags(): void
    {
        if (is_admin()) {
            return;
        }

        echo "\n<!-- RankSavvy SEO Meta Tags -->\n";
        
        $description = $this->getDescription();
        if ($description) {
            echo sprintf('<meta name="description" content="%s" />' . "\n", esc_attr($description));
        }

        $canonical = $this->getCanonicalUrl();
        if ($canonical) {
            echo sprintf('<link rel="canonical" href="%s" />' . "\n", esc_url($canonical));
        }

        $robots = $this->getRobotsMeta();
        if ($robots) {
            echo sprintf('<meta name="robots" content="%s" />' . "\n", esc_attr($robots));
        }

        // Webmaster Tools Verification Meta Tags
        $googleVerify = get_option('ranksavvy_google_verify', '');
        if (!empty($googleVerify)) {
            echo sprintf('<meta name="google-site-verification" content="%s" />' . "\n", esc_attr($googleVerify));
        }

        $bingVerify = get_option('ranksavvy_bing_verify', '');
        if (!empty($bingVerify)) {
            echo sprintf('<meta name="msvalidate.01" content="%s" />' . "\n", esc_attr($bingVerify));
        }

        $yandexVerify = get_option('ranksavvy_yandex_verify', '');
        if (!empty($yandexVerify)) {
            echo sprintf('<meta name="yandex-verification" content="%s" />' . "\n", esc_attr($yandexVerify));
        }

        $pinterestVerify = get_option('ranksavvy_pinterest_verify', '');
        if (!empty($pinterestVerify)) {
            echo sprintf('<meta name="pdomain" content="%s" />' . "\n", esc_attr($pinterestVerify));
        }

        echo "<!-- /RankSavvy SEO Meta Tags -->\n\n";
    }

    public function getDocumentTitle(): string
    {
        // MVP: Returns a standard or custom title depending on the current post/page
        // We can hook into get_post_meta here later for custom titles.
        if (is_singular()) {
            $customTitle = get_post_meta(get_the_ID(), '_ranksavvy_meta_title', true);
            if (!empty($customTitle)) {
                return $customTitle;
            }
            return get_the_title() . ' - ' . get_bloginfo('name');
        }
        
        return get_bloginfo('name') . ' - ' . get_bloginfo('description');
    }

    private function getDescription(): string
    {
        if (is_singular()) {
            $customDesc = get_post_meta(get_the_ID(), '_ranksavvy_meta_description', true);
            if (!empty($customDesc)) {
                return $customDesc;
            }
            // Fallback to excerpt
            return wp_trim_words(get_post_field('post_excerpt', get_the_ID()), 25);
        }
        
        if (is_front_page() || is_home()) {
            return get_bloginfo('description');
        }

        return '';
    }

    private function getCanonicalUrl(): string
    {
        if (is_singular()) {
            $customCanonical = get_post_meta(get_the_ID(), '_ranksavvy_canonical_url', true);
            if (!empty($customCanonical)) {
                return $customCanonical;
            }
        }
        
        global $wp;
        return home_url(add_query_arg([], $wp->request));
    }

    private function getRobotsMeta(): string
    {
        if (is_singular()) {
            $noindex = get_post_meta(get_the_ID(), '_ranksavvy_noindex', true);
            $nofollow = get_post_meta(get_the_ID(), '_ranksavvy_nofollow', true);
            
            $directives = [];
            if ($noindex === 'yes') {
                $directives[] = 'noindex';
            } else {
                $directives[] = 'index';
            }
            
            if ($nofollow === 'yes') {
                $directives[] = 'nofollow';
            } else {
                $directives[] = 'follow';
            }
            
            return implode(', ', $directives);
        }
        
        return 'index, follow';
    }
}
