<?php

namespace AmEveryWhere\Modules\Seo;

if (!defined('ABSPATH')) {
    exit;
}

class MetaTagsGenerator
{
    public function outputStandardMetaTags(): void
    {
        if (is_admin()) {
            return;
        }

        echo "\n<!-- AmEveryWhere SEO Meta Tags -->\n";
        
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
        $googleVerify = get_option('ameverywhere_google_verify', '');
        if (!empty($googleVerify)) {
            echo sprintf('<meta name="google-site-verification" content="%s" />' . "\n", esc_attr($googleVerify));
        }

        $bingVerify = get_option('ameverywhere_bing_verify', '');
        if (!empty($bingVerify)) {
            echo sprintf('<meta name="msvalidate.01" content="%s" />' . "\n", esc_attr($bingVerify));
        }

        $yandexVerify = get_option('ameverywhere_yandex_verify', '');
        if (!empty($yandexVerify)) {
            echo sprintf('<meta name="yandex-verification" content="%s" />' . "\n", esc_attr($yandexVerify));
        }

        $pinterestVerify = get_option('ameverywhere_pinterest_verify', '');
        if (!empty($pinterestVerify)) {
            echo sprintf('<meta name="pdomain" content="%s" />' . "\n", esc_attr($pinterestVerify));
        }

        echo "<!-- /AmEveryWhere SEO Meta Tags -->\n\n";
    }

    public function getDocumentTitle(): string
    {
        // MVP: Returns a standard or custom title depending on the current post/page
        // We can hook into get_post_meta here later for custom titles.
        if (is_singular()) {
            $customTitle = get_post_meta(get_the_ID(), '_ameverywhere_meta_title', true);
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
            $customDesc = get_post_meta(get_the_ID(), '_ameverywhere_meta_description', true);
            if (!empty($customDesc)) {
                return $customDesc;
            }
            // Fallback to excerpt, then post content snippet
            $excerpt = get_post_field('post_excerpt', get_the_ID());
            if (!empty($excerpt)) {
                return wp_trim_words($excerpt, 25);
            }
            return wp_trim_words(get_post_field('post_content', get_the_ID()), 25);
        }

        if (is_front_page() || is_home()) {
            return get_bloginfo('description');
        }

        // Taxonomy archive (category, tag, custom taxonomy)
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $termDesc = term_description($term->term_id, $term->taxonomy);
                $termDesc = wp_strip_all_tags($termDesc);
                if (!empty($termDesc)) {
                    return wp_trim_words($termDesc, 25);
                }
                // Fallback: generate from term name
                return sprintf(
                    'Browse all posts in the "%s" %s on %s.',
                    $term->name,
                    $term->taxonomy,
                    get_bloginfo('name')
                );
            }
        }

        // Author archive
        if (is_author()) {
            $author = get_queried_object();
            if ($author instanceof \WP_User) {
                $bio = get_the_author_meta('description', $author->ID);
                if (!empty($bio)) {
                    return wp_trim_words($bio, 25);
                }
                return sprintf(
                    'Posts by %s on %s.',
                    $author->display_name,
                    get_bloginfo('name')
                );
            }
        }

        // Post type archive
        if (is_post_type_archive()) {
            $postType = get_post_type_object((string) get_query_var('post_type'));
            if ($postType instanceof \WP_Post_Type) {
                if (!empty($postType->description)) {
                    return wp_trim_words($postType->description, 25);
                }
                return sprintf(
                    'Browse all %s on %s.',
                    strtolower($postType->labels->name ?? $postType->name),
                    get_bloginfo('name')
                );
            }
        }

        // Search results
        if (is_search()) {
            $query = get_search_query();
            if (!empty($query)) {
                return sprintf(
                    'Search results for "%s" on %s.',
                    esc_html($query),
                    get_bloginfo('name')
                );
            }
        }

        // Date archives
        if (is_date()) {
            if (is_day()) {
                $label = get_the_date();
            } elseif (is_month()) {
                $label = get_the_date('F Y');
            } else {
                $label = get_the_date('Y');
            }
            return sprintf(
                'Archive for %s on %s.',
                $label,
                get_bloginfo('name')
            );
        }

        return '';
    }

    private function getCanonicalUrl(): string
    {
        // ── Singular: honour per-post override, then use the clean permalink ──
        if (is_singular()) {
            // Suppress canonical on noindexed posts — contradictory signals confuse crawlers
            if (get_post_meta(get_the_ID(), '_ameverywhere_noindex', true) === 'yes') {
                return '';
            }
            $customCanonical = get_post_meta(get_the_ID(), '_ameverywhere_canonical_url', true);
            if (!empty($customCanonical)) {
                return $customCanonical;
            }
            return (string) get_permalink();
        }

        // ── Pages that will be noindexed: suppress canonical entirely ──
        if (is_search() || is_404() || is_date()) {
            return '';
        }

        // ── Paginated archives: self-referencing canonical per page number ──
        // get_pagenum_link() returns the correct URL for the current page in
        // an archive sequence (/page/2/, ?paged=2, etc.) without extra query strings.
        if (is_paged()) {
            return get_pagenum_link(get_query_var('paged'));
        }

        // ── First page of a taxonomy archive ──
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $link = get_term_link($term);
                return !is_wp_error($link) ? $link : '';
            }
        }

        // ── Post-type archive ──
        if (is_post_type_archive()) {
            $link = get_post_type_archive_link((string) get_query_var('post_type'));
            return $link ?: '';
        }

        // ── Author archive ──
        if (is_author()) {
            $author = get_queried_object();
            if ($author instanceof \WP_User) {
                return get_author_posts_url($author->ID);
            }
        }

        // ── Front page / blog index ──
        if (is_front_page() || is_home()) {
            return home_url('/');
        }

        // ── Fallback: clean path without query strings ──
        global $wp;
        return home_url($wp->request);
    }

    private function getRobotsMeta(): string
    {
        // ── Singular posts/pages: respect per-post noindex/nofollow flags ──
        if (is_singular()) {
            $noindex  = get_post_meta(get_the_ID(), '_ameverywhere_noindex', true);
            $nofollow = get_post_meta(get_the_ID(), '_ameverywhere_nofollow', true);

            $index  = ($noindex  === 'yes') ? 'noindex'  : 'index';
            $follow = ($nofollow === 'yes') ? 'nofollow' : 'follow';

            return "{$index}, {$follow}";
        }

        // ── 404 pages: always noindex ──
        if (is_404()) {
            return 'noindex, follow';
        }

        // ── Search results: thin/duplicate content — always noindex ──
        if (is_search()) {
            return 'noindex, follow';
        }

        // ── Paginated archives (page 2+): noindex to prevent duplication ──
        // Page 1 is canonical; subsequent pages are supplementary.
        if (is_paged()) {
            return 'noindex, follow';
        }

        // ── Date archives (day/month/year): thin content — noindex ──
        if (is_date()) {
            return 'noindex, follow';
        }

        // ── All other contexts (category, tag, author, CPT archives, home) ──
        return 'index, follow';
    }
}
