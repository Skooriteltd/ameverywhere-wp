<?php

namespace RankSavvy\Modules\TechnicalSeo;

/**
 * Monitors and logs front-end 404 errors safely and efficiently.
 *
 * Architecture:
 *   - Frontend: Pushes 404 details into a lightweight transient buffer (zero DB writes).
 *   - Background: A WP-Cron job flushes the buffer into the database every hour.
 *   - Limits: Caps stored logs at MAX_LOGS (100) to prevent database ballooning.
 *   - No SHOW TABLES: Table existence is checked once on activation and cached.
 */
class ErrorMonitor
{
    private const OPTION_KEY = 'ranksavvy_404_logs';
    private const BUFFER_TRANSIENT = 'ranksavvy_404_buffer';
    private const TABLE_EXISTS_OPTION = 'ranksavvy_404_table_exists';
    private const MAX_LOGS = 100;
    private const MAX_BUFFER = 50; // Cap buffer size to prevent transient bloat
    private const FLUSH_HOOK = 'ranksavvy_flush_404_buffer';

    /**
     * Boot the error monitor — register the background flush cron.
     */
    public function boot(): void
    {
        // Schedule the hourly background flush if not already scheduled
        if (!wp_next_scheduled(self::FLUSH_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::FLUSH_HOOK);
        }
        add_action(self::FLUSH_HOOK, [$this, 'flushBufferToDatabase']);
    }

    /**
     * Intercept front-end queries and buffer the 404 — zero database writes.
     */
    public function log404Errors(): void
    {
        if (!is_404()) {
            return;
        }

        // Avoid logging common static assets and hack attempts
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (empty($uri) || preg_match('/\.(jpg|jpeg|png|gif|ico|css|js|map|xml|txt|woff2|woff|ttf)$/i', $uri)) {
            return;
        }

        // Filter out obvious bot probes
        if (strpos($uri, 'wp-admin') !== false || strpos($uri, 'wp-login') !== false || strpos($uri, '.env') !== false) {
            return;
        }

        $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        // Push into the in-memory transient buffer (no DB write during HTTP request)
        $buffer = get_transient(self::BUFFER_TRANSIENT);
        if (!is_array($buffer)) {
            $buffer = [];
        }

        // Deduplicate in the buffer: if the URI already exists, increment hits
        $found = false;
        foreach ($buffer as &$entry) {
            if ($entry['uri'] === $uri) {
                $entry['hits']++;
                $entry['last_hit'] = current_time('mysql');
                $entry['referer'] = $referer ?: $entry['referer'];
                $entry['user_agent'] = $userAgent;
                $found = true;
                break;
            }
        }
        unset($entry);

        if (!$found && count($buffer) < self::MAX_BUFFER) {
            $buffer[] = [
                'uri'        => $uri,
                'hits'       => 1,
                'referer'    => $referer,
                'user_agent' => $userAgent,
                'last_hit'   => current_time('mysql'),
            ];
        }

        // Store back — short TTL, flushed by cron within the hour
        set_transient(self::BUFFER_TRANSIENT, $buffer, 2 * HOUR_IN_SECONDS);
    }

    /**
     * Background cron callback: flush the transient buffer into the database.
     * Runs outside the HTTP request lifecycle — safe for batch DB writes.
     */
    public function flushBufferToDatabase(): void
    {
        $buffer = get_transient(self::BUFFER_TRANSIENT);
        if (empty($buffer) || !is_array($buffer)) {
            return;
        }

        // Check table existence from cached option (set during activation)
        if (get_option(self::TABLE_EXISTS_OPTION) !== 'yes') {
            // Re-verify once and cache the result
            global $wpdb;
            $table = $wpdb->prefix . 'ranksavvy_404_logs';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                update_option(self::TABLE_EXISTS_OPTION, 'yes', false);
            } else {
                return; // Table doesn't exist, skip
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ranksavvy_404_logs';

        foreach ($buffer as $entry) {
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE uri = %s", $entry['uri']));

            if ($existing) {
                $wpdb->update(
                    $table,
                    [
                        'hits'       => intval($existing->hits) + intval($entry['hits']),
                        'last_hit'   => $entry['last_hit'],
                        'referer'    => $entry['referer'] ?: $existing->referer,
                        'user_agent' => $entry['user_agent'],
                    ],
                    ['id' => $existing->id]
                );
            } else {
                $wpdb->insert(
                    $table,
                    [
                        'id'         => uniqid('err_404_', false),
                        'uri'        => $entry['uri'],
                        'hits'       => intval($entry['hits']),
                        'referer'    => $entry['referer'],
                        'user_agent' => $entry['user_agent'],
                        'last_hit'   => $entry['last_hit'],
                    ]
                );
            }
        }

        // Trim oldest logs if total exceeds MAX_LOGS
        $totalLogs = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($totalLogs > self::MAX_LOGS) {
            $wpdb->query(
                "DELETE FROM $table WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM $table ORDER BY last_hit DESC LIMIT " . self::MAX_LOGS . "
                    ) as temp
                )"
            );
        }

        // Clear the buffer after successful flush
        delete_transient(self::BUFFER_TRANSIENT);
    }

    /**
     * Mark the 404 table as existing (called from Installer on activation).
     */
    public static function markTableExists(): void
    {
        update_option(self::TABLE_EXISTS_OPTION, 'yes', false);
    }

    /**
     * Expose REST routes for 404 monitoring.
     */
    public function registerRoutes(): void
    {
        register_rest_route('ranksavvy/v1', '/errors/404', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get404LogsEndpoint'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'clear404LogsEndpoint'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function get404LogsEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        // Force-flush any pending buffer so admin sees the latest data
        $this->flushBufferToDatabase();

        global $wpdb;
        $table = $wpdb->prefix . 'ranksavvy_404_logs';

        if (get_option(self::TABLE_EXISTS_OPTION) !== 'yes') {
            return rest_ensure_response([]);
        }

        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY last_hit DESC", ARRAY_A);

        // Run legacy migration on the first load if table is empty but options has logs
        $legacyLogs = get_option(self::OPTION_KEY);
        if (empty($rows) && !empty($legacyLogs) && is_array($legacyLogs)) {
            foreach ($legacyLogs as $log) {
                $wpdb->insert($table, [
                    'id'         => $log['id'] ?? uniqid('err_404_', false),
                    'uri'        => $log['uri'] ?? '',
                    'hits'       => intval($log['hits'] ?? 1),
                    'referer'    => $log['referer'] ?? '',
                    'user_agent' => $log['user_agent'] ?? '',
                    'last_hit'   => $log['last_hit'] ?? current_time('mysql'),
                ]);
            }
            $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY last_hit DESC", ARRAY_A);
            delete_option(self::OPTION_KEY);
        }

        // Standardize types
        if (!empty($rows)) {
            foreach ($rows as &$row) {
                $row['hits'] = intval($row['hits']);
            }
        } else {
            $rows = [];
        }

        return rest_ensure_response($rows);
    }

    public function clear404LogsEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $id = sanitize_text_field($params['id'] ?? '');

        global $wpdb;
        $table = $wpdb->prefix . 'ranksavvy_404_logs';

        if (empty($id)) {
            // Clear all logs
            $wpdb->query("DELETE FROM $table");
            return rest_ensure_response(['success' => true, 'logs' => []]);
        }

        // Clear specific log item
        $wpdb->delete($table, ['id' => $id]);

        $updatedLogs = $wpdb->get_results("SELECT * FROM $table ORDER BY last_hit DESC", ARRAY_A);
        if (!empty($updatedLogs)) {
            foreach ($updatedLogs as &$row) {
                $row['hits'] = intval($row['hits']);
            }
        } else {
            $updatedLogs = [];
        }

        return rest_ensure_response(['success' => true, 'logs' => $updatedLogs]);
    }

    /**
     * Cleanup on plugin deactivation: clear the scheduled flush event.
     */
    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled(self::FLUSH_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::FLUSH_HOOK);
        }
    }
}
