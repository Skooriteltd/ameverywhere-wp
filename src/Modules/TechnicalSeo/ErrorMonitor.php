<?php

namespace RankSavvy\Modules\TechnicalSeo;

/**
 * Monitors and logs front-end 404 errors safely and efficiently.
 * Limits option bloat by enforcing a strict cap on stored logs.
 * Exposes a REST API for displaying and managing logs.
 */
class ErrorMonitor
{
    private const OPTION_KEY = 'ranksavvy_404_logs';
    private const MAX_LOGS = 100; // Cap log records strictly to avoid database ballooning

    /**
     * Intercept front-end queries and log if it is a 404 error.
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

        // Filter out obvious bot probes for WP admin scripts
        if (strpos($uri, 'wp-admin') !== false || strpos($uri, 'wp-login') !== false || strpos($uri, '.env') !== false) {
            return;
        }

        $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        $logs = get_option(self::OPTION_KEY, []);
        
        $foundKey = null;
        foreach ($logs as $key => $log) {
            if ($log['uri'] === $uri) {
                $foundKey = $key;
                break;
            }
        }

        if ($foundKey !== null) {
            // Update hit count and timestamp
            $logs[$foundKey]['hits']++;
            $logs[$foundKey]['last_hit'] = current_time('mysql');
            $logs[$foundKey]['referer'] = $referer ?: $logs[$foundKey]['referer'];
            $logs[$foundKey]['user_agent'] = $userAgent;
        } else {
            // Add new log entry
            $logs[] = [
                'id'         => uniqid('err_404_', false),
                'uri'        => $uri,
                'hits'       => 1,
                'referer'    => $referer,
                'user_agent' => $userAgent,
                'last_hit'   => current_time('mysql'),
            ];
        }

        // Enforce MAX_LOGS cap: Sort by last hit date descending and slice
        usort($logs, function ($a, $b) {
            return strcmp($b['last_hit'], $a['last_hit']);
        });

        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, 0, self::MAX_LOGS);
        }

        update_option(self::OPTION_KEY, $logs);
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
        $logs = get_option(self::OPTION_KEY, []);
        return rest_ensure_response($logs);
    }

    public function clear404LogsEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $id = sanitize_text_field($params['id'] ?? '');

        if (empty($id)) {
            // Clear all logs
            update_option(self::OPTION_KEY, []);
            return rest_ensure_response(['success' => true, 'logs' => []]);
        }

        // Clear specific log item
        $logs = get_option(self::OPTION_KEY, []);
        $filtered = array_filter($logs, function ($log) use ($id) {
            return ($log['id'] ?? '') !== $id;
        });

        $filtered = array_values($filtered);
        update_option(self::OPTION_KEY, $filtered);

        return rest_ensure_response(['success' => true, 'logs' => $filtered]);
    }
}
