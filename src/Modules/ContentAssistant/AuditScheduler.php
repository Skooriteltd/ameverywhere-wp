<?php

namespace AmEveryWhere\Modules\ContentAssistant;

/**
 * AuditScheduler
 *
 * Schedules recurring lightweight SEO audits and emails results to the admin.
 * Stores the last 12 audit runs for trend tracking.
 *
 * BL-023
 */
class AuditScheduler
{
    private const RESULTS_OPTION  = 'ameverywhere_last_audit_results';
    private const HISTORY_OPTION  = 'ameverywhere_audit_history';
    private const SCHEDULE_OPTION = 'ameverywhere_audit_schedule';
    private const CRON_HOOK       = 'ameverywhere_scheduled_audit';
    private const HISTORY_MAX     = 12;

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action(self::CRON_HOOK, [$this, 'runScheduledAudit']);
    }

    // ── REST routes ───────────────────────────────────────────────────────────

    public function registerRoutes(): void
    {
        $adminCap = fn() => current_user_can('manage_options');

        register_rest_route('ameverywhere/v1', '/audit/schedule', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSchedule'],
                'permission_callback' => $adminCap,
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'saveSchedule'],
                'permission_callback' => $adminCap,
            ],
        ]);

        register_rest_route('ameverywhere/v1', '/audit/results', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getResults'],
            'permission_callback' => $adminCap,
        ]);

        register_rest_route('ameverywhere/v1', '/audit/run-now', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'runNow'],
            'permission_callback' => $adminCap,
        ]);
    }

    // ── REST callbacks ────────────────────────────────────────────────────────

    public function getSchedule(\WP_REST_Request $request): \WP_REST_Response
    {
        $config   = $this->getConfig();
        $nextRun  = wp_next_scheduled(self::CRON_HOOK);
        $lastRun  = get_option(self::RESULTS_OPTION, []);

        return rest_ensure_response([
            'frequency'      => $config['frequency'],
            'email_enabled'  => $config['email_enabled'],
            'email_address'  => $config['email_address'],
            'next_run'       => $nextRun ? date('Y-m-d H:i:s', $nextRun) : null,
            'last_run'       => $lastRun['timestamp'] ?? null,
        ]);
    }

    public function saveSchedule(\WP_REST_Request $request): \WP_REST_Response
    {
        $params    = $request->get_json_params();
        $frequency = in_array($params['frequency'] ?? '', ['weekly', 'monthly'], true)
            ? $params['frequency'] : 'weekly';
        $emailEnabled = (bool) ($params['email_enabled'] ?? true);
        $emailAddress = sanitize_email($params['email_address'] ?? get_option('admin_email'));

        $config = compact('frequency', 'emailEnabled', 'emailAddress');
        $config['email_enabled'] = $emailEnabled;
        $config['email_address'] = $emailAddress;
        unset($config['emailEnabled'], $config['emailAddress']);

        update_option(self::SCHEDULE_OPTION, $config);
        $this->setupSchedule($frequency);

        return rest_ensure_response(['success' => true, 'config' => $config]);
    }

    public function getResults(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'last_results' => get_option(self::RESULTS_OPTION, null),
            'history'      => get_option(self::HISTORY_OPTION, []),
        ]);
    }

    public function runNow(\WP_REST_Request $request): \WP_REST_Response
    {
        wp_schedule_single_event(time() + 2, self::CRON_HOOK);
        return rest_ensure_response(['success' => true, 'message' => 'Audit will run momentarily.']);
    }

    // ── Audit logic ───────────────────────────────────────────────────────────

    public function runScheduledAudit(): void
    {
        $issues   = 0;
        $warnings = 0;
        $passed   = 0;
        $details  = [];

        // 1. Missing meta descriptions
        $missingMeta = (new \WP_Query([
            'post_type' => ['post', 'page'], 'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [['key' => '_ameverywhere_meta_description', 'compare' => 'NOT EXISTS']],
        ]))->found_posts;
        if ($missingMeta > 0) { $warnings++; $details['missing_meta_descriptions'] = $missingMeta; }
        else { $passed++; }

        // 2. Missing alt text
        $missingAlt = (new \WP_Query([
            'post_type' => 'attachment', 'post_status' => 'inherit',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'posts_per_page' => 1,
            'meta_query' => [['key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS']],
        ]))->found_posts;
        if ($missingAlt > 0) { $warnings++; $details['missing_alt_text'] = $missingAlt; }
        else { $passed++; }

        // 3. 404 count
        global $wpdb;
        $logTable = $wpdb->prefix . 'ameverywhere_404_logs';
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '$logTable'");
        $count404 = $tableExists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $logTable") : 0;
        if ($count404 > 20) { $issues++; $details['404_count'] = $count404; }
        elseif ($count404 > 5) { $warnings++; $details['404_count'] = $count404; }
        else { $passed++; }

        // 4. Noindex on front page
        $frontPageId = (int) get_option('page_on_front');
        $frontNoindex = $frontPageId > 0 ? get_post_meta($frontPageId, '_ameverywhere_noindex', true) : '';
        if ($frontNoindex === 'yes') { $issues++; $details['front_page_noindex'] = true; }
        else { $passed++; }

        // 5. SSL
        if (strpos(site_url(), 'https://') !== 0) { $issues++; $details['no_ssl'] = true; }
        else { $passed++; }

        $results = [
            'timestamp' => current_time('mysql'),
            'summary'   => ['issues' => $issues, 'warnings' => $warnings, 'passed' => $passed],
            'details'   => $details,
        ];

        update_option(self::RESULTS_OPTION, $results);

        // Append to history (keep last 12)
        $history   = (array) get_option(self::HISTORY_OPTION, []);
        $history[] = ['timestamp' => $results['timestamp'], 'summary' => $results['summary']];
        update_option(self::HISTORY_OPTION, array_slice($history, -self::HISTORY_MAX));

        // Email if enabled
        $config = $this->getConfig();
        if ($config['email_enabled']) {
            wp_mail(
                $config['email_address'],
                '[AmEveryWhere] Scheduled SEO Audit Results',
                $this->formatEmailBody($results),
                ['Content-Type: text/html; charset=UTF-8']
            );
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function setupSchedule(string $frequency): void
    {
        // Clear existing
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }

        $recurrence = ($frequency === 'monthly') ? 'monthly' : 'weekly';
        wp_schedule_event(time() + 60, $recurrence, self::CRON_HOOK);
    }

    public function formatEmailBody(array $results): string
    {
        $s = $results['summary'];
        $body  = '<h2>AmEveryWhere SEO Audit Results</h2>';
        $body .= "<p>Run at: <strong>{$results['timestamp']}</strong></p>";
        $body .= '<table border="1" cellpadding="6" style="border-collapse:collapse">';
        $body .= '<tr><th>Metric</th><th>Count</th></tr>';
        $body .= '<tr><td>🔴 Critical Issues</td><td>' . $s['issues']   . '</td></tr>';
        $body .= '<tr><td>⚠️ Warnings</td><td>'        . $s['warnings'] . '</td></tr>';
        $body .= '<tr><td>✅ Passed</td><td>'           . $s['passed']   . '</td></tr>';
        $body .= '</table>';

        if (!empty($results['details'])) {
            $body .= '<h3>Details</h3><ul>';
            foreach ($results['details'] as $k => $v) {
                $body .= '<li>' . esc_html(str_replace('_', ' ', $k)) . ': <strong>' . esc_html((string) $v) . '</strong></li>';
            }
            $body .= '</ul>';
        }

        $body .= '<p><a href="' . esc_url(admin_url('admin.php?page=ameverywhere')) . '">View full audit in AmEveryWhere →</a></p>';
        return $body;
    }

    private function getConfig(): array
    {
        return array_merge(
            ['frequency' => 'weekly', 'email_enabled' => true, 'email_address' => get_option('admin_email')],
            (array) get_option(self::SCHEDULE_OPTION, [])
        );
    }
}
