<?php

namespace AmEveryWhere\Modules\Breadcrumbs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders front-end breadcrumb navigation and registers:
 * - Shortcode: [ameverywhere_breadcrumbs]
 * - PHP function: ameverywhere_breadcrumbs()
 * 
 * Also outputs BreadcrumbList JSON-LD schema automatically.
 */
class BreadcrumbRenderer
{
    private string $separator;
    private string $homeLabel;

    public function __construct()
    {
        $this->separator = get_option('ameverywhere_breadcrumb_separator', '›');
        $this->homeLabel = get_option('ameverywhere_breadcrumb_home_label', 'Home');
    }

    /**
     * Register WordPress hooks.
     */
    public function register(): void
    {
        add_shortcode('ameverywhere_breadcrumbs', [$this, 'renderShortcode']);
        add_action('wp_footer', [$this, 'outputSchema']);

        $autoInsert = get_option('ameverywhere_breadcrumb_auto_insert', 'none');
        if ($autoInsert === 'before_content') {
            add_filter('the_content', [$this, 'autoInsertBeforeContent'], 9);
        }
    }

    /**
     * Filter callback to automatically insert breadcrumbs before content.
     */
    public function autoInsertBeforeContent(string $content): string
    {
        if (is_singular() && in_the_loop() && is_main_query()) {
            return $this->render() . $content;
        }
        return $content;
    }

    /**
     * Shortcode callback.
     */
    public function renderShortcode($atts): string
    {
        return $this->render();
    }

    /**
     * Build the breadcrumb trail and return HTML.
     */
    public function render(): string
    {
        if (is_front_page()) {
            return '';
        }

        $items = $this->buildTrail();
        if (empty($items)) {
            return '';
        }

        $sep = '<span class="ameverywhere-breadcrumb-sep" aria-hidden="true"> ' . esc_html($this->separator) . ' </span>';
        $parts = [];

        foreach ($items as $i => $item) {
            $isLast = ($i === count($items) - 1);

            if ($isLast) {
                $parts[] = '<span class="ameverywhere-breadcrumb-current" aria-current="page">' . esc_html($item['name']) . '</span>';
            } else {
                $parts[] = '<a href="' . esc_url($item['url']) . '" class="ameverywhere-breadcrumb-link">' . esc_html($item['name']) . '</a>';
            }
        }

        $html = '<nav class="ameverywhere-breadcrumbs" aria-label="Breadcrumb">';
        $html .= '<ol class="ameverywhere-breadcrumb-list">';
        $html .= '<li>' . implode('</li><li>' . $sep, $parts) . '</li>';
        $html .= '</ol>';
        $html .= '</nav>';

        // Inline minimal styles
        $html .= '<style>.ameverywhere-breadcrumbs{font-size:14px;line-height:1.4;margin:8px 0 16px;color:#64748b}.ameverywhere-breadcrumb-list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:0}.ameverywhere-breadcrumb-list li{display:inline-flex;align-items:center}.ameverywhere-breadcrumb-link{color:#3b82f6;text-decoration:none}.ameverywhere-breadcrumb-link:hover{text-decoration:underline}.ameverywhere-breadcrumb-sep{margin:0 6px;color:#94a3b8}.ameverywhere-breadcrumb-current{color:#334155;font-weight:500}</style>';

        return $html;
    }

    /**
     * Build the breadcrumb trail as an array of ['name' => ..., 'url' => ...].
     */
    private function buildTrail(): array
    {
        $trail = [];

        // Home
        $trail[] = [
            'name' => $this->homeLabel,
            'url'  => home_url('/'),
        ];

        if (is_singular()) {
            $post = get_queried_object();

            // Add category for posts
            if ($post->post_type === 'post') {
                $categories = get_the_category($post->ID);
                if (!empty($categories)) {
                    $cat = $categories[0];
                    // Add parent categories
                    $parents = $this->getCategoryParents($cat);
                    $trail = array_merge($trail, $parents);

                    $trail[] = [
                        'name' => $cat->name,
                        'url'  => get_category_link($cat->term_id),
                    ];
                }
            }

            // For hierarchical post types (pages), add ancestors
            if (is_post_type_hierarchical($post->post_type)) {
                $ancestors = get_post_ancestors($post->ID);
                $ancestors = array_reverse($ancestors);
                foreach ($ancestors as $ancestorId) {
                    $trail[] = [
                        'name' => get_the_title($ancestorId),
                        'url'  => get_permalink($ancestorId),
                    ];
                }
            }

            // Current item
            $trail[] = [
                'name' => get_the_title($post->ID),
                'url'  => get_permalink($post->ID),
            ];
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();

            if (is_category()) {
                $parents = $this->getCategoryParents($term);
                $trail = array_merge($trail, $parents);
            }

            $trail[] = [
                'name' => $term->name,
                'url'  => get_term_link($term),
            ];
        } elseif (is_post_type_archive()) {
            $trail[] = [
                'name' => post_type_archive_title('', false),
                'url'  => get_post_type_archive_link(get_query_var('post_type')),
            ];
        } elseif (is_search()) {
            $trail[] = [
                'name' => 'Search: ' . get_search_query(),
                'url'  => get_search_link(),
            ];
        } elseif (is_404()) {
            $trail[] = [
                'name' => 'Page Not Found',
                'url'  => '',
            ];
        }

        return $trail;
    }

    /**
     * Get parent categories recursively.
     */
    private function getCategoryParents($category): array
    {
        $parents = [];

        if ($category->parent) {
            $parent = get_category($category->parent);
            if ($parent && !is_wp_error($parent)) {
                $parents = array_merge($this->getCategoryParents($parent), $parents);
                $parents[] = [
                    'name' => $parent->name,
                    'url'  => get_category_link($parent->term_id),
                ];
            }
        }

        return $parents;
    }

    /**
     * Output BreadcrumbList JSON-LD schema in the footer.
     */
    public function outputSchema(): void
    {
        if (is_front_page() || is_admin()) {
            return;
        }

        $trail = $this->buildTrail();
        if (count($trail) < 2) {
            return;
        }

        $items = [];
        foreach ($trail as $i => $item) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
                'item'     => $item['url'] ?: null,
            ];
        }

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}
