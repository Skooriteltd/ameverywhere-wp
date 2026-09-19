<?php

namespace AmEveryWhere\Modules\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SeoAuditHistoryLog
 *
 * BL-042: Maintains a tamper-evident audit trail of all AmEveryWhere SEO setting
 * changes. Records who changed what, when, and what the previous value was.
 */
class SeoAuditHistoryLog
{
    private const TABLE_NAME   = 'ameverywhere_audit_log';
    private const LOG_MAX_ROWS = 10000;

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        $this->hookMetaChanges();
    }

    public static function createTable(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            post_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            action      VARCHAR(64) NOT NULL,
            field       VARCHAR(128) NOT NULL,
            old_value   TEXT,
            new_value   TEXT,
            context     VARCHAR(255),
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private function hookMetaChanges(): void
    {
        // Track changes to all _ameverywhere_* post meta
        add_action('updated_post_meta', [$this, 'onMetaUpdated'], 10, 4);
        add_action('added_post_meta', [$this, 'onMetaAdded'], 10, 4);
        add_action('deleted_post_meta', [$this, 'onMetaDeleted'], 10, 4);

        // Track changes to ameverywhere_ options
        add_action('updated_option', [$this, 'onOptionUpdated'], 10, 3);
    }

    // ── Hooks ─────────────────────────────────────────────────────────────────

    public function onMetaUpdated($metaId, int $postId, string $metaKey, $newValue): void
    {
        if (strpos($metaKey, '_ameverywhere_') !== 0) {
            return;
        }

        $oldValue = get_post_meta($postId, $metaKey, true);
        $this->log($postId, 'meta_updated', $metaKey, (string) $oldValue, (string) $newValue);
    }

    public function onMetaAdded($metaId, int $postId, string $metaKey, $metaValue): void
    {
        if (strpos($metaKey, '_ameverywhere_') !== 0) {
            return;
        }

        $this->log($postId, 'meta_added', $metaKey, '', (string) $metaValue);
    }

    public function onMetaDeleted($metaIds, int $postId, string $metaKey, $metaValue): void
    {
        if (strpos($metaKey, '_ameverywhere_') !== 0) {
            return;
        }

        $this->log($postId, 'meta_deleted', $metaKey, (string) $metaValue, '');
    }

    public function onOptionUpdated(string $option, $oldValue, $newValue): void
    {
        if (strpos($option, 'ameverywhere_') !== 0) {
            return;
        }

        // Skip high-frequency transients
        if (strpos($option, 'ameverywhere_audit_log') !== false) {
            return;
        }

        $this->log(0, 'option_updated', $option, maybe_serialize($oldValue), maybe_serialize($newValue), 'site_option');
    }

    // ── REST routes ───────────────────────────────────────────────────────────

    public function registerRoutes(): void
    {
        $adminCap = fn() => current_user_can('manage_options');

        register_rest_route('ameverywhere/v1', '/audit-log', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getLogs'],
            'permission_callback' => $adminCap,
            'args' => [
                'post_id'  => ['sanitize_callback' => 'absint', 'default' => 0],
                'page'     => ['sanitize_callback' => 'absint', 'default' => 1],
                'per_page' => ['sanitize_callback' => 'absint', 'default' => 50],
            ],
        ]);

        register_rest_route('ameverywhere/v1', '/audit-log/export', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'exportCsv'],
            'permission_callback' => $adminCap,
        ]);
    }

    public function getLogs(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_NAME;
        $postId  = (int) $request->get_param('post_id');
        $page    = max(1, (int) $request->get_param('page'));
        $perPage = min(200, max(1, (int) $request->get_param('per_page')));
        $offset  = ($page - 1) * $perPage;

        if ($postId > 0) {
            $total = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE post_id = %d", $postId)
            );
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE post_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $postId,
                    $perPage,
                    $offset
                ),
                ARRAY_A
            );
        } else {
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $perPage,
                    $offset
                ),
                ARRAY_A
            );
        }

        // Enrich with user display name
        foreach ($rows as &$row) {
            $row['user_name'] = $row['user_id'] > 0 ? get_userdata((int) $row['user_id'])?->display_name ?? '' : 'System';
        }

        return rest_ensure_response([
            'items'       => $rows,
            'total'       => $total,
            'total_pages' => (int) ceil($total / $perPage),
            'page'        => $page,
        ]);
    }

    public function exportCsv(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $rows  = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 5000", ARRAY_A);

        $lines   = ['id,user_id,post_id,action,field,old_value,new_value,context,created_at'];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $r));
        }

        $response = rest_ensure_response(implode("\n", $lines));
        $response->header('Content-Type', 'text/csv; charset=utf-8');
        $response->header('Content-Disposition', 'attachment; filename=ameverywhere-audit-log.csv');
        return $response;
    }

    // ── Core log writer ───────────────────────────────────────────────────────

    public function log(int $postId, string $action, string $field, string $oldValue, string $newValue, string $context = ''): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $wpdb->insert($table, [
            'user_id'    => (int) get_current_user_id(),
            'post_id'    => $postId,
            'action'     => $action,
            'field'      => $field,
            'old_value'  => mb_substr($oldValue, 0, 1000),
            'new_value'  => mb_substr($newValue, 0, 1000),
            'context'    => $context,
            'created_at' => current_time('mysql'),
        ]);

        // Prune oldest rows if table grows too large
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > self::LOG_MAX_ROWS) {
            $deleteLimit = (int) ($count - self::LOG_MAX_ROWS + 1);
            $wpdb->query(
                $wpdb->prepare("DELETE FROM {$table} ORDER BY id ASC LIMIT %d", $deleteLimit)
            );
        }
    }
}
