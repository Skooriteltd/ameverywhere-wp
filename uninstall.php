<?php
/**
 * AmEveryWhere Uninstall
 *
 * Runs when the plugin is deleted from the WordPress admin.
 * Removes all plugin options, custom database tables, and post meta.
 *
 * @package AmEveryWhere
 */

// Exit if not called by WordPress uninstall process.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// ── 1. Remove all plugin options (including legacy ranksavvy_ options) ────────
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ameverywhere_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ranksavvy_%'");

// ── 2. Remove all plugin post meta (including legacy _ranksavvy_ meta) ────────
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ameverywhere_%'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ranksavvy_%'");

// ── 3. Drop custom database tables ───────────────────────────────────────────
$tables = [
    // AmEveryWhere tables
    $wpdb->prefix . 'ameverywhere_redirects',
    $wpdb->prefix . 'ameverywhere_404_logs',
    $wpdb->prefix . 'ameverywhere_sitemap_settings',
    $wpdb->prefix . 'ameverywhere_links',
    $wpdb->prefix . 'ameverywhere_ai_usage',
    $wpdb->prefix . 'ameverywhere_usage_metering',
    $wpdb->prefix . 'ameverywhere_audit_log',
    $wpdb->prefix . 'ameverywhere_rank_history',
    // Legacy RankSavvy tables
    $wpdb->prefix . 'ranksavvy_redirects',
    $wpdb->prefix . 'ranksavvy_404_logs',
    $wpdb->prefix . 'ranksavvy_sitemap_settings',
    $wpdb->prefix . 'ranksavvy_links',
    $wpdb->prefix . 'ranksavvy_ai_usage',
    $wpdb->prefix . 'ranksavvy_usage_metering',
    $wpdb->prefix . 'ranksavvy_audit_log',
    $wpdb->prefix . 'ranksavvy_rank_history',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

// ── 4. Remove scheduled cron events ──────────────────────────────────────────
$cronHooks = [
    // AmEveryWhere cron hooks
    'ameverywhere_scan_orphaned',
    'ameverywhere_stale_cornerstone_check',
    'ameverywhere_weekly_audit',
    'ameverywhere_monthly_audit',
    'ameverywhere_rank_check',
    'ameverywhere_404_cleanup',
    'ameverywhere_run_technical_audit',
    // Legacy RankSavvy cron hooks
    'ranksavvy_scan_orphaned',
    'ranksavvy_stale_cornerstone_check',
    'ranksavvy_weekly_audit',
    'ranksavvy_monthly_audit',
    'ranksavvy_rank_check',
    'ranksavvy_404_cleanup',
    'ranksavvy_run_technical_audit',
];

foreach ($cronHooks as $hook) {
    $timestamp = wp_next_scheduled($hook);
    if ($timestamp) {
        wp_unschedule_event($timestamp, $hook);
    }
    wp_clear_scheduled_hook($hook);
}

// ── 5. Remove transients ─────────────────────────────────────────────────────
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ameverywhere_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ameverywhere_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ranksavvy_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ranksavvy_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_psi_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_psi_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gsc_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_gsc_%'");

// ── 6. Remove custom user capabilities ───────────────────────────────────────
$seoCapabilities = [
    'manage_seo',
    'view_seo_reports',
    'manage_redirects',
    'edit_seo_meta',
];

$roles = ['administrator', 'editor', 'author', 'contributor'];
foreach ($roles as $roleName) {
    $role = get_role($roleName);
    if ($role) {
        foreach ($seoCapabilities as $cap) {
            $role->remove_cap($cap);
        }
    }
}

// ── 7. Multisite: run on every blog in batches ──────────────────────────────
if (is_multisite()) {
    $batchSize = 100;
    $offset    = 0;

    do {
        $sites = get_sites(['number' => $batchSize, 'offset' => $offset, 'fields' => 'ids']);
        if (empty($sites)) {
            break;
        }

        foreach ($sites as $blogId) {
            switch_to_blog($blogId);
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ameverywhere_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ranksavvy_%'");
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ameverywhere_%'");
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ranksavvy_%'");

            foreach ($tables as $subTable) {
                $wpdb->query("DROP TABLE IF EXISTS `{$subTable}`");
            }
            restore_current_blog();
        }

        $offset += $batchSize;
    } while (count($sites) === $batchSize);

    // Remove network options
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'ameverywhere_%'");
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'ranksavvy_%'");
}
