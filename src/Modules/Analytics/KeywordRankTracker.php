<?php

namespace AmEveryWhere\Modules\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * KeywordRankTracker
 *
 * BL-031: Tracks keyword position over time using SerpApi or a configurable
 * SERP provider. Stores historical position data for trend analysis.
 */
class KeywordRankTracker
{
    private const KEYWORDS_OPTION = 'ameverywhere_tracked_keywords';
    private const HISTORY_TABLE   = 'ameverywhere_rank_history';
    private const CRON_HOOK       = 'ameverywhere_rank_check';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action(self::CRON_HOOK, [$this, 'checkAllKeywords']);
        // Schedule if not already
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function createTable(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::HISTORY_TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE IF NOT EXISTS {$table} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword    VARCHAR(255) NOT NULL,
            engine     VARCHAR(20) NOT NULL DEFAULT 'google',
            position   SMALLINT UNSIGNED,
            url        TEXT,
            checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_keyword (keyword(191)),
            KEY idx_checked_at (checked_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function registerRoutes(): void
    {
        $reportCap = fn() => current_user_can('view_seo_reports') || current_user_can('manage_options');
        $adminCap  = fn() => current_user_can('manage_options');

        register_rest_route('ameverywhere/v1', '/rank-tracker/keywords', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getKeywords'],
                'permission_callback' => $adminCap,
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'saveKeywords'],
                'permission_callback' => $adminCap,
            ],
        ]);

        register_rest_route('ameverywhere/v1', '/rank-tracker/history', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getHistory'],
            'permission_callback' => $reportCap,
            'args'                => [
                'keyword' => ['sanitize_callback' => 'sanitize_text_field'],
                'days'    => ['default' => 30, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route('ameverywhere/v1', '/rank-tracker/check-now', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'checkNow'],
            'permission_callback' => $adminCap,
        ]);
    }

    public function getKeywords(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'keywords' => get_option(self::KEYWORDS_OPTION, []),
        ]);
    }

    public function saveKeywords(\WP_REST_Request $request): \WP_REST_Response
    {
        $params   = $request->get_json_params();
        $keywords = array_map('sanitize_text_field', $params['keywords'] ?? []);
        $keywords = array_filter(array_unique(array_slice($keywords, 0, 100)));

        update_option(self::KEYWORDS_OPTION, array_values($keywords));
        return rest_ensure_response(['success' => true, 'count' => count($keywords)]);
    }

    public function getHistory(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::HISTORY_TABLE;
        $keyword = $request->get_param('keyword');
        $days    = min(180, max(1, (int) $request->get_param('days')));

        $where  = $wpdb->prepare('WHERE checked_at >= %s', date('Y-m-d', strtotime("-{$days} days")));
        if ($keyword) {
            $where .= $wpdb->prepare(' AND keyword = %s', $keyword);
        }

        $rows = $wpdb->get_results(
            "SELECT keyword, position, url, checked_at FROM {$table} {$where} ORDER BY keyword, checked_at DESC",
            ARRAY_A
        );

        // Group by keyword
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['keyword']][] = ['position' => (int) $row['position'], 'url' => $row['url'], 'date' => $row['checked_at']];
        }

        return rest_ensure_response(['history' => $grouped, 'days' => $days]);
    }

    public function checkNow(\WP_REST_Request $request): \WP_REST_Response
    {
        $params  = $request->get_json_params();
        $keyword = sanitize_text_field($params['keyword'] ?? '');

        $keywords = $keyword ? [$keyword] : get_option(self::KEYWORDS_OPTION, []);

        if (empty($keywords)) {
            return new \WP_Error('no_keywords', 'No keywords to check.', ['status' => 400]);
        }

        $results = $this->checkKeywords(array_slice($keywords, 0, 5));
        return rest_ensure_response(['results' => $results]);
    }

    public function checkAllKeywords(): void
    {
        $keywords = get_option(self::KEYWORDS_OPTION, []);
        $this->checkKeywords($keywords);
    }

    private function checkKeywords(array $keywords): array
    {
        $apiKey  = (string) get_option('ameverywhere_serpapi_key', '');
        $results = [];

        foreach ($keywords as $keyword) {
            if (empty($apiKey)) {
                $results[$keyword] = ['error' => 'SerpApi key not configured. Set ameverywhere_serpapi_key option.'];
                continue;
            }

            $response = wp_remote_get(add_query_arg([
                'api_key' => $apiKey,
                'engine'  => 'google',
                'q'       => urlencode($keyword),
                'gl'      => get_option('ameverywhere_rank_country', 'us'),
                'hl'      => get_option('ameverywhere_rank_language', 'en'),
                'num'     => 100,
            ], 'https://serpapi.com/search.json'), ['timeout' => 20]);

            if (is_wp_error($response)) {
                $results[$keyword] = ['error' => $response->get_error_message()];
                continue;
            }

            $data     = json_decode(wp_remote_retrieve_body($response), true);
            $organic  = $data['organic_results'] ?? [];
            $siteHost = parse_url(home_url(), PHP_URL_HOST);
            $position = null;
            $matchUrl = null;

            foreach ($organic as $i => $result) {
                $resultHost = parse_url($result['link'] ?? '', PHP_URL_HOST);
                if ($resultHost && str_contains($resultHost, str_replace('www.', '', $siteHost))) {
                    $position = $i + 1;
                    $matchUrl = $result['link'];
                    break;
                }
            }

            $this->savePosition($keyword, $position, $matchUrl);
            $results[$keyword] = ['position' => $position, 'url' => $matchUrl];
        }

        return $results;
    }

    private function savePosition(string $keyword, ?int $position, ?string $url): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . self::HISTORY_TABLE, [
            'keyword'    => $keyword,
            'engine'     => 'google',
            'position'   => $position,
            'url'        => $url,
            'checked_at' => current_time('mysql'),
        ]);
    }
}
