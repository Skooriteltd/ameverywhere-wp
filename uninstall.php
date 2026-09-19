<?php
/**
 * AmEveryWhere uninstall routine.
 *
 * Plugin data is retained by default. An administrator must explicitly enable
 * `ameverywhere_delete_data_on_uninstall` before deletion to be permitted.
 *
 * @package AmEveryWhere
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clear runtime work for one site and, when explicitly requested, remove only
 * data owned by this plugin. Table names are calculated after switch_to_blog().
 */
$ameverywhereUninstallSite = static function (bool $deleteData): void {
    global $wpdb;

    $cronHooks = [
        'ameverywhere_process_job',
        'ameverywhere_scan_orphaned',
        'ameverywhere_stale_cornerstone_check',
        'ameverywhere_weekly_audit',
        'ameverywhere_monthly_audit',
        'ameverywhere_scheduled_audit',
        'ameverywhere_rank_check',
        'ameverywhere_404_cleanup',
        'ameverywhere_apply_retention',
        'ameverywhere_run_technical_audit',
        'ameverywhere_blc_scan_batch',
        'ameverywhere_usage_metering_weekly',
    ];

    foreach ($cronHooks as $hook) {
        wp_clear_scheduled_hook($hook);
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions($hook);
            as_unschedule_all_actions($hook, [], 'ameverywhere');
        }
    }

    if (!$deleteData) {
        return;
    }

    $uploads = wp_upload_dir();
    $uploadsBase = trailingslashit((string) ($uploads['basedir'] ?? ''));
    $backups = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
            '_ameverywhere_compression_original'
        )
    );
    foreach ($backups as $backup) {
        $backup = (string) $backup;
        if ($uploadsBase !== '' && str_starts_with($backup, $uploadsBase) && is_file($backup)) {
            wp_delete_file($backup);
        }
    }

    $likeOptions = $wpdb->esc_like('ameverywhere_') . '%';
    $likeMeta = $wpdb->esc_like('_ameverywhere_') . '%';
    $likeTransient = $wpdb->esc_like('_transient_ameverywhere_') . '%';
    $likeTransientTimeout = $wpdb->esc_like('_transient_timeout_ameverywhere_') . '%';

    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $likeOptions)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $likeTransient)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $likeTransientTimeout)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $likeMeta)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    $tables = [
        $wpdb->prefix . 'ameverywhere_redirects',
        $wpdb->prefix . 'ameverywhere_404_logs',
        $wpdb->prefix . 'ameverywhere_sitemap_settings',
        $wpdb->prefix . 'ameverywhere_links',
        $wpdb->prefix . 'ameverywhere_ai_usage',
        $wpdb->prefix . 'ameverywhere_usage_metering',
        $wpdb->prefix . 'ameverywhere_audit_log',
        $wpdb->prefix . 'ameverywhere_rank_history',
        $wpdb->prefix . 'ameverywhere_broken_links',
    ];
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }
};

$deleteData = get_option('ameverywhere_delete_data_on_uninstall', 'no') === 'yes';
$ameverywhereUninstallSite($deleteData);

foreach (['administrator', 'editor', 'author', 'contributor'] as $roleName) {
    $role = get_role($roleName);
    if ($role) {
        foreach (['manage_seo', 'view_seo_reports', 'manage_redirects', 'edit_seo_meta'] as $capability) {
            $role->remove_cap($capability);
        }
    }
}

if (is_multisite()) {
    $siteIds = get_sites(['fields' => 'ids', 'number' => 0]);
    $currentBlogId = get_current_blog_id();
    foreach ($siteIds as $siteId) {
        if ((int) $siteId === $currentBlogId) {
            continue;
        }
        switch_to_blog((int) $siteId);
        $ameverywhereUninstallSite($deleteData);
        restore_current_blog();
    }

    if ($deleteData) {
        $likeNetworkOptions = $wpdb->esc_like('ameverywhere_') . '%';
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $likeNetworkOptions)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}
