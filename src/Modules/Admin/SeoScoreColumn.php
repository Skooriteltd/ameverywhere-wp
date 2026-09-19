<?php

namespace AmEveryWhere\Modules\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds an SEO Score column to the WordPress Posts and Pages list tables.
 * Displays a color-coded indicator based on the completeness of SEO meta fields.
 */
class SeoScoreColumn
{
    /**
     * Register hooks for both Posts and Pages list tables.
     */
    public function register(): void
    {
        foreach (['post', 'page'] as $postType) {
            add_filter("manage_{$postType}_posts_columns", [$this, 'addColumn']);
            add_action("manage_{$postType}_posts_custom_column", [$this, 'renderColumn'], 10, 2);
            add_filter("manage_edit-{$postType}_sortable_columns", [$this, 'makeSortable']);
        }

        add_action('admin_head', [$this, 'injectColumnStyles']);
    }

    /**
     * Add the SEO Score column header.
     */
    public function addColumn(array $columns): array
    {
        $newColumns = [];
        foreach ($columns as $key => $label) {
            $newColumns[$key] = $label;
            // Insert our column right after the title column
            if ($key === 'title') {
                $newColumns['ameverywhere_seo'] = 'SEO';
            }
        }
        return $newColumns;
    }

    /**
     * Render the SEO Score indicator for each row.
     */
    public function renderColumn(string $column, int $postId): void
    {
        if ($column !== 'ameverywhere_seo') {
            return;
        }

        $score = $this->calculateScore($postId);
        $color = $score >= 80 ? '#22c55e' : ($score >= 50 ? '#f59e0b' : '#ef4444');
        $label = $score >= 80 ? 'Good' : ($score >= 50 ? 'OK' : 'Poor');

        printf(
            '<span title="%s — %d/100" style="display:inline-flex;align-items:center;gap:5px;">
                <span style="display:inline-block;width:12px;height:12px;border-radius:50%%;background:%s;"></span>
                <span style="font-size:12px;font-weight:500;color:%s;">%d</span>
            </span>',
            esc_attr($label),
            $score,
            esc_attr($color),
            esc_attr($color),
            $score
        );
    }

    /**
     * Make the column sortable (optional enhancement).
     */
    public function makeSortable(array $columns): array
    {
        $columns['ameverywhere_seo'] = 'ameverywhere_seo';
        return $columns;
    }

    /**
     * Calculate a server-side SEO score based on saved meta fields.
     * This is a lightweight approximation of the full client-side score.
     */
    private function calculateScore(int $postId): int
    {
        $post = get_post($postId);
        if (!$post) {
            return 0;
        }

        $earned = 0;
        $total = 0;

        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);
        $wordCount = str_word_count($content);
        $focusKeyword = get_post_meta($postId, '_ameverywhere_focus_keyword', true);
        $metaTitle = get_post_meta($postId, '_ameverywhere_meta_title', true);
        $metaDescription = get_post_meta($postId, '_ameverywhere_meta_description', true);

        $effectiveTitle = $metaTitle ?: $title;

        // Focus keyword set (10 pts)
        $total += 10;
        if ($focusKeyword) {
            $earned += 10;

            // Keyword in title (10 pts)
            $total += 10;
            if (stripos($title, $focusKeyword) !== false) {
                $earned += 10;
            }

            // Keyword in content (10 pts)
            $total += 10;
            if (stripos($content, $focusKeyword) !== false) {
                $earned += 10;
            }
        }

        // Meta title length (15 pts)
        $total += 15;
        $titleLen = mb_strlen($effectiveTitle);
        if ($titleLen > 0 && $titleLen <= 60) {
            $earned += 15;
        }

        // Meta description set and proper length (20 pts)
        $total += 20;
        $descLen = mb_strlen($metaDescription);
        if ($descLen >= 120 && $descLen <= 160) {
            $earned += 20;
        } elseif ($descLen > 0) {
            $earned += 10; // Partial credit for having one at all
        }

        // Content length (15 pts)
        $total += 15;
        if ($wordCount >= 300) {
            $earned += 15;
        } elseif ($wordCount >= 150) {
            $earned += 8;
        }

        // Has featured image (10 pts)
        $total += 10;
        if (has_post_thumbnail($postId)) {
            $earned += 10;
        }

        // Has internal/external links (10 pts)
        $total += 10;
        if (preg_match('/<a\s+[^>]*href/i', $post->post_content)) {
            $earned += 10;
        }

        return $total > 0 ? (int) round(($earned / $total) * 100) : 0;
    }

    /**
     * Inject minimal CSS for the column width.
     */
    public function injectColumnStyles(): void
    {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['edit-post', 'edit-page'], true)) {
            return;
        }

        echo '<style>.column-ameverywhere_seo { width: 60px; text-align: center; }</style>';
    }
}
