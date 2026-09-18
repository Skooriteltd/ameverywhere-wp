<?php

namespace AmEveryWhere\Modules\TechnicalSeo;

/**
 * BrokenLinkChecker
 *
 * Scans all published post/page content for dead outbound links.
 * Batches posts across staggered WP-Cron events so it never bogs
 * down the server.
 *
 * Results table: ameverywhere_broken_links
 */
class BrokenLinkChecker
{
    private const RESULTS_TABLE   = 'ameverywhere_broken_links';
    private const PROGRESS_OPTION = 'ameverywhere_blc_progress';
    private const SCAN_HOOK       = 'ameverywhere_blc_scan_batch';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action(self::SCAN_HOOK, [$this, 'processBatch'], 10, 1);
    }

    public static function createTable(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::RESULTS_TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE IF NOT EXISTS {$table} (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id      BIGINT UNSIGNED NOT NULL,
            url          TEXT NOT NULL,
            http_code    SMALLINT UNSIGNED DEFAULT NULL,
            anchor_text  VARCHAR(255) DEFAULT '',
            last_checked DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved     TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_resolved (resolved)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function registerRoutes(): void
    {
        $adminCap  = fn() => current_user_can('manage_options');
        $reportCap = fn() => current_user_can('view_seo_reports') || current_user_can('manage_options');

        register_rest_route('ameverywhere/v1', '/broken-links', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getResults'],
            'permission_callback' => $reportCap,
            'args'                => [
                'per_page' => ['default' => 20, 'sanitize_callback' => 'absint'],
                'page'     => ['default' => 1,  'sanitize_callback' => 'absint'],
                'resolved' => ['default' => '0'],
            ],
        ]);

        register_rest_route('ameverywhere/v1', '/broken-links/scan', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'startScan'],
            'permission_callback' => $adminCap,
        ]);

        register_rest_route('ameverywhere/v1', '/broken-links/progress', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => fn() => rest_ensure_response(
                get_option(self::PROGRESS_OPTION, ['status' => 'idle', 'scanned' => 0, 'total' => 0, 'found' => 0])
            ),
            'permission_callback' => $reportCap,
        ]);

        register_rest_route('ameverywhere/v1', '/broken-links/(?P<id>\d+)/resolve', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'resolveLink'],
            'permission_callback' => $adminCap,
        ]);

        register_rest_route('ameverywhere/v1', '/broken-links/recheck', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'recheckLink'],
            'permission_callback' => $adminCap,
        ]);
    }

    public function startScan(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . self::RESULTS_TABLE;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
            $wpdb->query("DELETE FROM {$table} WHERE resolved = 0");
        }

        $postIds = get_posts([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
        ]);

        $total  = count($postIds);
        $chunks = array_chunk($postIds, 10);

        update_option(self::PROGRESS_OPTION, [
            'status'  => 'running',
            'scanned' => 0,
            'total'   => $total,
            'found'   => 0,
            'started' => current_time('mysql'),
        ]);

        $delay = 0;
        foreach ($chunks as $chunk) {
            wp_schedule_single_event(time() + $delay, self::SCAN_HOOK, [$chunk]);
            $delay += 15;
        }

        return rest_ensure_response([
            'success'     => true,
            'total_posts' => $total,
            'batches'     => count($chunks),
            'message'     => "Scan started — {$total} posts queued across " . count($chunks) . " batches.",
        ]);
    }

    public function processBatch(array $postIds): void
    {
        global $wpdb;
        $table    = $wpdb->prefix . self::RESULTS_TABLE;
        $progress = get_option(self::PROGRESS_OPTION, []);
        $found    = (int) ($progress['found'] ?? 0);

        foreach ($postIds as $postId) {
            $content = get_post_field('post_content', $postId);
            if (empty($content)) {
                continue;
            }

            $links = $this->extractLinks($content);

            foreach ($links as $link) {
                $url    = $link['url'];
                $anchor = $link['anchor'];
                $code   = $this->checkUrl($url);

                if ($code === null || $code < 400) {
                    continue;
                }

                $wpdb->insert($table, [
                    'post_id'      => $postId,
                    'url'          => $url,
                    'http_code'    => $code,
                    'anchor_text'  => mb_substr($anchor, 0, 255),
                    'last_checked' => current_time('mysql'),
                    'resolved'     => 0,
                ]);
                $found++;
            }
        }

        $scanned = (int) ($progress['scanned'] ?? 0) + count($postIds);
        $total   = (int) ($progress['total'] ?? 0);
        $isDone  = ($scanned >= $total);

        update_option(self::PROGRESS_OPTION, [
            'status'  => $isDone ? 'done' : 'running',
            'scanned' => $scanned,
            'total'   => $total,
            'found'   => $found,
            'done_at' => $isDone ? current_time('mysql') : null,
        ]);
    }

    public function getResults(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table    = $wpdb->prefix . self::RESULTS_TABLE;
        $perPage  = min(100, max(1, (int) $request->get_param('per_page')));
        $page     = max(1, (int) $request->get_param('page'));
        $resolved = $request->get_param('resolved') === '1' ? 1 : 0;
        $offset   = ($page - 1) * $perPage;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return rest_ensure_response(['items' => [], 'total' => 0]);
        }

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE resolved = %d",
            $resolved
        ));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, p.post_title
             FROM {$table} b
             LEFT JOIN {$wpdb->posts} p ON p.ID = b.post_id
             WHERE b.resolved = %d
             ORDER BY b.http_code DESC, b.last_checked DESC
             LIMIT %d OFFSET %d",
            $resolved, $perPage, $offset
        ), ARRAY_A);

        foreach ($rows as &$row) {
            $row['post_url'] = get_permalink((int) $row['post_id']);
            $row['edit_url'] = get_edit_post_link((int) $row['post_id'], 'raw');
        }

        return rest_ensure_response(['items' => $rows, 'total' => $total]);
    }

    public function resolveLink(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $id = (int) $request->get_param('id');
        $wpdb->update($wpdb->prefix . self::RESULTS_TABLE, ['resolved' => 1], ['id' => $id]);
        return rest_ensure_response(['success' => true]);
    }

    public function recheckLink(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $params = $request->get_json_params();
        $id     = (int) ($params['id'] ?? 0);
        $url    = esc_url_raw($params['url'] ?? '');

        if (!$id || !$url) {
            return new \WP_Error('missing_params', 'id and url are required.', ['status' => 400]);
        }

        $code = $this->checkUrl($url);
        $wpdb->update(
            $wpdb->prefix . self::RESULTS_TABLE,
            ['http_code' => $code, 'last_checked' => current_time('mysql')],
            ['id' => $id]
        );

        return rest_ensure_response(['success' => true, 'http_code' => $code, 'broken' => $code >= 400]);
    }

    private function extractLinks(string $html): array
    {
        $links = [];
        $dom   = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);
        $anchors = $dom->getElementsByTagName('a');
        $siteHost = parse_url(home_url(), PHP_URL_HOST);

        foreach ($anchors as $a) {
            $href = $a->getAttribute('href');
            if (empty($href) || !filter_var($href, FILTER_VALIDATE_URL)) {
                continue;
            }
            $host = parse_url($href, PHP_URL_HOST);
            // Skip internal links
            if ($host && str_contains($siteHost ?? '', str_replace('www.', '', $host))) {
                continue;
            }
            $links[] = ['url' => $href, 'anchor' => trim($a->textContent)];
        }

        return $links;
    }

    private function checkUrl(string $url): ?int
    {
        static $cache = [];
        if (isset($cache[$url])) {
            return $cache[$url];
        }

        $args = [
            'timeout'     => 10,
            'redirection' => 5,
            'user-agent'  => 'Mozilla/5.0 (compatible; AmEveryWhere-BLC/1.0)',
        ];

        $response = wp_remote_head($url, $args);
        if (is_wp_error($response)) {
            $response = wp_remote_get($url, $args);
        }

        if (is_wp_error($response)) {
            $cache[$url] = null;
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $cache[$url] = $code;
        return $code;
    }
}
