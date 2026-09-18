<?php

namespace AmEveryWhere\Modules\TechnicalSeo;

/**
 * OrphanedContentFinder
 *
 * Identifies published posts/pages that receive zero inbound internal links
 * from other published content. Results are stored in post-meta and refreshed
 * via a background WP-Cron job so the scan never blocks a page request.
 *
 * BL-002
 */
class OrphanedContentFinder
{
    private const INBOUND_META_KEY  = '_ameverywhere_inbound_links';
    private const LAST_RUN_OPTION   = 'ameverywhere_orphan_scan_last_run';
    private const CRON_HOOK         = 'ameverywhere_scan_orphaned';
    private const CHUNK_SIZE        = 200;

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action(self::CRON_HOOK, [$this, 'scanOrphanedContent']);
    }

    // ── REST routes ───────────────────────────────────────────────────────────

    public function registerRoutes(): void
    {
        register_rest_route('ameverywhere/v1', '/orphaned-content', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getOrphanedContent'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args'                => [
                'page'      => ['default' => 1,     'sanitize_callback' => 'absint'],
                'per_page'  => ['default' => 20,    'sanitize_callback' => 'absint'],
                'post_type' => ['default' => 'any', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route('ameverywhere/v1', '/orphaned-content/refresh', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'refreshScan'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    // ── REST callbacks ────────────────────────────────────────────────────────

    public function getOrphanedContent(\WP_REST_Request $request): \WP_REST_Response
    {
        $page      = max(1, (int) $request->get_param('page'));
        $perPage   = min(100, max(1, (int) $request->get_param('per_page')));
        $postType  = $request->get_param('post_type');

        $args = [
            'post_type'      => ($postType === 'any') ? get_post_types(['public' => true]) : [$postType],
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => self::INBOUND_META_KEY,
                    'value'   => '0',
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => self::INBOUND_META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ];

        $query = new \WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            $items[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'url'           => get_permalink($post->ID),
                'post_type'     => $post->post_type,
                'date'          => $post->post_date,
                'inbound_links' => (int) get_post_meta($post->ID, self::INBOUND_META_KEY, true),
            ];
        }

        return rest_ensure_response([
            'items'       => $items,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page'        => $page,
            'per_page'    => $perPage,
            'last_scan'   => get_option(self::LAST_RUN_OPTION, null),
        ]);
    }

    public function refreshScan(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + 5, self::CRON_HOOK);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => 'Orphaned content scan scheduled. Results will be available within a few minutes.',
        ]);
    }

    // ── Cron callback ─────────────────────────────────────────────────────────

    /**
     * Scan all published content and record the inbound-link count per post.
     *
     * Strategy: for each target post, count how many OTHER published posts
     * contain a link to its permalink in their post_content. Uses a SQL LIKE
     * query per post to avoid loading all content into PHP memory.
     */
    public function scanOrphanedContent(): void
    {
        global $wpdb;

        $postTypes = array_values(get_post_types(['public' => true]));

        // Fetch all published post IDs and permalinks in chunks
        $offset = 0;

        do {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $placeholders = implode(',', array_fill(0, count($postTypes), '%s'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $posts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts
                     WHERE post_status = 'publish'
                       AND post_type IN ($placeholders)
                     LIMIT %d OFFSET %d",
                    ...array_merge($postTypes, [self::CHUNK_SIZE, $offset])
                )
            );

            if (empty($posts)) {
                break;
            }

            foreach ($posts as $post) {
                $permalink   = get_permalink($post->ID);
                $relPath     = wp_make_link_relative($permalink);

                // Count other published posts whose content links to this post.
                // We check both the absolute URL and the relative path.
                $count = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM $wpdb->posts
                         WHERE post_status = 'publish'
                           AND ID != %d
                           AND (post_content LIKE %s OR post_content LIKE %s)",
                        $post->ID,
                        '%' . $wpdb->esc_like($permalink) . '%',
                        '%' . $wpdb->esc_like($relPath) . '%'
                    )
                );

                update_post_meta($post->ID, self::INBOUND_META_KEY, $count);
            }

            $offset += self::CHUNK_SIZE;

        } while (count($posts) === self::CHUNK_SIZE);

        update_option(self::LAST_RUN_OPTION, current_time('mysql'));
    }
}
