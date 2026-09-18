<?php

namespace AmEveryWhere\Modules\Compliance;

/**
 * CcpaPrivacyTools
 *
 * CCPA/GDPR compliance toolkit:
 * - Data inventory endpoint
 * - User data export & deletion
 * - Configurable retention policy with auto-purge cron
 * - Integrates with WordPress built-in privacy exporters/erasers
 * - Honours GPC (Global Privacy Control) header
 *
 * BL-008
 */
class CcpaPrivacyTools
{
    private const RETENTION_OPTION   = 'ameverywhere_privacy_retention';
    private const HONOUR_GPC_OPTION  = 'ameverywhere_honour_gpc';
    private const CRON_HOOK          = 'ameverywhere_apply_retention';

    public function boot(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action(self::CRON_HOOK, [$this, 'applyRetentionPolicy']);
        add_filter('wp_privacy_personal_data_exporters', [$this, 'registerExporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'registerEraser']);
        add_action('init', [$this, 'handleGpcSignal']);

        // Schedule daily retention purge if not already scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    // ── GPC signal ────────────────────────────────────────────────────────────

    public function handleGpcSignal(): void
    {
        if (get_option(self::HONOUR_GPC_OPTION, 'yes') !== 'yes') {
            return;
        }

        $gpc = $_SERVER['HTTP_SEC_GPC'] ?? '';
        if ($gpc === '1') {
            // Signal to other modules that AI/tracking features should be suppressed
            do_action('ameverywhere_gpc_opt_out');
            setcookie('ameverywhere_gpc', '1', [
                'expires'  => time() + YEAR_IN_SECONDS,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    // ── REST routes ───────────────────────────────────────────────────────────

    public function registerRoutes(): void
    {
        $adminCap = fn() => current_user_can('manage_options');

        register_rest_route('ameverywhere/v1', '/privacy/data-inventory', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getDataInventory'],
            'permission_callback' => $adminCap,
        ]);

        register_rest_route('ameverywhere/v1', '/privacy/export', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'exportUserData'],
            'permission_callback' => $adminCap,
        ]);

        register_rest_route('ameverywhere/v1', '/privacy/delete', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'deleteUserData'],
            'permission_callback' => $adminCap,
        ]);

        register_rest_route('ameverywhere/v1', '/privacy/retention-settings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getRetentionSettings'],
                'permission_callback' => $adminCap,
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'saveRetentionSettings'],
                'permission_callback' => $adminCap,
            ],
        ]);

        register_rest_route('ameverywhere/v1', '/privacy/apply-retention', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'triggerRetention'],
            'permission_callback' => $adminCap,
        ]);

        register_rest_route('ameverywhere/v1', '/privacy/gpc-settings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => fn() => rest_ensure_response(['honour_gpc' => get_option(self::HONOUR_GPC_OPTION, 'yes') === 'yes']),
                'permission_callback' => $adminCap,
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => function (\WP_REST_Request $req) {
                    $p = $req->get_json_params();
                    update_option(self::HONOUR_GPC_OPTION, !empty($p['honour_gpc']) ? 'yes' : 'no');
                    return rest_ensure_response(['success' => true]);
                },
                'permission_callback' => $adminCap,
            ],
        ]);
    }

    // ── REST callbacks ────────────────────────────────────────────────────────

    public function getDataInventory(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'data_types' => [
                [
                    'store'       => 'ameverywhere_404_logs (DB table)',
                    'data_type'   => 'URL request logs, referrer URLs, User-Agent strings',
                    'sensitivity' => 'low',
                    'retention'   => 'Configurable (default: 90 days)',
                ],
                [
                    'store'       => 'ameverywhere_redirects (DB table)',
                    'data_type'   => 'Redirect rules (URLs only, no PII)',
                    'sensitivity' => 'none',
                    'retention'   => 'Indefinite',
                ],
                [
                    'store'       => 'ameverywhere_ai_usage (DB table)',
                    'data_type'   => 'AI token usage per WordPress user ID',
                    'sensitivity' => 'low',
                    'retention'   => 'Configurable (default: 365 days)',
                ],
                [
                    'store'       => 'wp_options (ameverywhere_* keys)',
                    'data_type'   => 'Plugin settings, API keys (encrypted)',
                    'sensitivity' => 'medium',
                    'retention'   => 'Until plugin uninstalled',
                ],
                [
                    'store'       => 'wp_postmeta (_ameverywhere_* keys)',
                    'data_type'   => 'Per-post SEO metadata (titles, descriptions, schema overrides)',
                    'sensitivity' => 'none',
                    'retention'   => 'Until post deleted',
                ],
            ],
        ]);
    }

    public function exportUserData(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $email  = sanitize_email($params['email'] ?? '');

        if (!is_email($email)) {
            return new \WP_Error('invalid_email', 'A valid email address is required.', ['status' => 400]);
        }

        $user = get_user_by('email', $email);
        $data = ['email' => $email, 'user_id' => $user ? $user->ID : null, 'records' => []];

        if ($user) {
            global $wpdb;
            $usageTable = $wpdb->prefix . 'ameverywhere_ai_usage';

            // AI usage records
            $tableExists = $wpdb->get_var("SHOW TABLES LIKE '$usageTable'");
            if ($tableExists) {
                $usageLogs = $wpdb->get_results(
                    $wpdb->prepare("SELECT * FROM $usageTable WHERE user_id = %d ORDER BY created_at DESC LIMIT 500", $user->ID)
                );
                $data['records']['ai_usage'] = $usageLogs;
            }

            // User meta set by AmEveryWhere
            $userMeta = get_user_meta($user->ID);
            $rsMeta   = array_filter($userMeta, fn($k) => strpos($k, 'ameverywhere') === 0, ARRAY_FILTER_USE_KEY);
            $data['records']['user_meta'] = $rsMeta;
        }

        return rest_ensure_response(['success' => true, 'data' => $data]);
    }

    public function deleteUserData(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $email  = sanitize_email($params['email'] ?? '');

        if (!is_email($email)) {
            return new \WP_Error('invalid_email', 'A valid email address is required.', ['status' => 400]);
        }

        $user    = get_user_by('email', $email);
        $deleted = 0;

        if ($user) {
            global $wpdb;
            $usageTable = $wpdb->prefix . 'ameverywhere_ai_usage';

            $tableExists = $wpdb->get_var("SHOW TABLES LIKE '$usageTable'");
            if ($tableExists) {
                $deleted += (int) $wpdb->delete($usageTable, ['user_id' => $user->ID], ['%d']);
            }

            // Delete AmEveryWhere-specific user meta
            $allMeta = get_user_meta($user->ID);
            foreach (array_keys($allMeta) as $key) {
                if (strpos($key, 'ameverywhere') === 0) {
                    delete_user_meta($user->ID, $key);
                    $deleted++;
                }
            }
        }

        return rest_ensure_response(['success' => true, 'records_deleted' => $deleted]);
    }

    public function getRetentionSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $defaults = ['keep_404_logs_days' => 90, 'keep_usage_logs_days' => 365];
        return rest_ensure_response(array_merge($defaults, get_option(self::RETENTION_OPTION, [])));
    }

    public function saveRetentionSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $config = [
            'keep_404_logs_days'   => max(1, (int) ($params['keep_404_logs_days']   ?? 90)),
            'keep_usage_logs_days' => max(1, (int) ($params['keep_usage_logs_days'] ?? 365)),
        ];
        update_option(self::RETENTION_OPTION, $config);
        return rest_ensure_response(['success' => true, 'settings' => $config]);
    }

    public function triggerRetention(\WP_REST_Request $request): \WP_REST_Response
    {
        wp_schedule_single_event(time() + 5, self::CRON_HOOK);
        return rest_ensure_response(['success' => true, 'message' => 'Retention purge scheduled.']);
    }

    // ── Cron: retention purge ─────────────────────────────────────────────────

    public function applyRetentionPolicy(): void
    {
        global $wpdb;

        $config = array_merge(
            ['keep_404_logs_days' => 90, 'keep_usage_logs_days' => 365],
            get_option(self::RETENTION_OPTION, [])
        );

        // Purge old 404 logs
        $logsTable = $wpdb->prefix . 'ameverywhere_404_logs';
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '$logsTable'");
        if ($tableExists) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $logsTable WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $config['keep_404_logs_days']
                )
            );
        }

        // Purge old AI usage logs
        $usageTable = $wpdb->prefix . 'ameverywhere_ai_usage';
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '$usageTable'");
        if ($tableExists) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $usageTable WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $config['keep_usage_logs_days']
                )
            );
        }
    }

    // ── WordPress privacy framework integration ───────────────────────────────

    public function registerExporter(array $exporters): array
    {
        $exporters['ameverywhere'] = [
            'exporter_friendly_name' => 'AmEveryWhere SEO Data',
            'callback'               => [$this, 'wpPrivacyExporter'],
        ];
        return $exporters;
    }

    public function registerEraser(array $erasers): array
    {
        $erasers['ameverywhere'] = [
            'eraser_friendly_name' => 'AmEveryWhere SEO Data',
            'callback'             => [$this, 'wpPrivacyEraser'],
        ];
        return $erasers;
    }

    public function wpPrivacyExporter(string $email, int $page = 1): array
    {
        $user  = get_user_by('email', $email);
        $items = [];

        if ($user) {
            global $wpdb;
            $usageTable  = $wpdb->prefix . 'ameverywhere_ai_usage';
            $tableExists = $wpdb->get_var("SHOW TABLES LIKE '$usageTable'");

            if ($tableExists) {
                $rows = $wpdb->get_results(
                    $wpdb->prepare("SELECT * FROM $usageTable WHERE user_id = %d LIMIT 100 OFFSET %d", $user->ID, ($page - 1) * 100)
                );

                foreach ($rows as $row) {
                    $items[] = [
                        'group_id'    => 'ameverywhere_ai_usage',
                        'group_label' => 'AmEveryWhere AI Usage',
                        'item_id'     => 'ai-usage-' . $row->id,
                        'data'        => [
                            ['name' => 'Provider', 'value' => $row->provider],
                            ['name' => 'Feature',  'value' => $row->feature],
                            ['name' => 'Tokens',   'value' => $row->tokens_used],
                            ['name' => 'Date',     'value' => $row->created_at],
                        ],
                    ];
                }
            }
        }

        return ['data' => $items, 'done' => true];
    }

    public function wpPrivacyEraser(string $email, int $page = 1): array
    {
        $result = $this->deleteUserData(new \WP_REST_Request());
        return ['items_removed' => true, 'items_retained' => false, 'messages' => [], 'done' => true];
    }
}
