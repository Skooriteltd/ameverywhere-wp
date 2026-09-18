<?php

namespace AmEveryWhere\Core\Database;

/**
 * Handles database table installation and schema migration for AmEveryWhere.
 * Leverages dbDelta for non-destructive updates and automatically migrates
 * legacy AmEveryWhere tables, options, and post metadata.
 */
class Installer
{
    /**
     * Run the schema installation and legacy data migration.
     */
    public function install(): void
    {
        global $wpdb;

        // 1. Migrate legacy AmEveryWhere database tables, options, and postmeta if present
        $this->migrateLegacyDataAndTables();

        $charsetCollate = $wpdb->get_charset_collate();

        $tableRedirects = $wpdb->prefix . 'ameverywhere_redirects';
        $table404Logs = $wpdb->prefix . 'ameverywhere_404_logs';
        $tableSitemapSettings = $wpdb->prefix . 'ameverywhere_sitemap_settings';
        $tableLinks = $wpdb->prefix . 'ameverywhere_links';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Redirects table schema
        $sqlRedirects = "CREATE TABLE $tableRedirects (
            id varchar(50) NOT NULL,
            source text NOT NULL,
            target text NOT NULL,
            code int NOT NULL DEFAULT 301,
            is_regex tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY source (source(191)),
            KEY is_regex (is_regex)
        ) $charsetCollate;";

        // 2. 404 logs table schema
        $sql404Logs = "CREATE TABLE $table404Logs (
            id varchar(50) NOT NULL,
            uri varchar(255) NOT NULL,
            hits int NOT NULL DEFAULT 1,
            referer text DEFAULT NULL,
            user_agent text DEFAULT NULL,
            last_hit datetime NOT NULL,
            resolved tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY uri (uri(191))
        ) $charsetCollate;";

        // 3. Sitemap settings table schema
        $sqlSitemapSettings = "CREATE TABLE $tableSitemapSettings (
            setting_key varchar(100) NOT NULL,
            setting_value text NOT NULL,
            PRIMARY KEY  (setting_key)
        ) $charsetCollate;";

        // 4. Internal Link Index table
        $sqlLinks = "CREATE TABLE $tableLinks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            target_url varchar(255) NOT NULL,
            anchor_text varchar(255) DEFAULT '',
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY target_url (target_url(191))
        ) $charsetCollate;";

        dbDelta($sqlRedirects);
        dbDelta($sql404Logs);
        dbDelta($sqlSitemapSettings);
        dbDelta($sqlLinks);
    }

    /**
     * Non-destructive migration of legacy AmEveryWhere tables, options, and postmeta to AmEveryWhere.
     */
    public function migrateLegacyDataAndTables(): void
    {
        global $wpdb;

        // 1. Rename existing legacy tables if new table does not exist
        $tableMappings = [
            $wpdb->prefix . 'ranksavvy_redirects'        => $wpdb->prefix . 'ameverywhere_redirects',
            $wpdb->prefix . 'ranksavvy_404_logs'         => $wpdb->prefix . 'ameverywhere_404_logs',
            $wpdb->prefix . 'ranksavvy_sitemap_settings' => $wpdb->prefix . 'ameverywhere_sitemap_settings',
            $wpdb->prefix . 'ranksavvy_links'            => $wpdb->prefix . 'ameverywhere_links',
            $wpdb->prefix . 'ranksavvy_ai_usage'         => $wpdb->prefix . 'ameverywhere_ai_usage',
            $wpdb->prefix . 'ranksavvy_usage_metering'   => $wpdb->prefix . 'ameverywhere_usage_metering',
            $wpdb->prefix . 'ranksavvy_audit_log'        => $wpdb->prefix . 'ameverywhere_audit_log',
            $wpdb->prefix . 'ranksavvy_rank_history'     => $wpdb->prefix . 'ameverywhere_rank_history',
        ];

        foreach ($tableMappings as $oldTable => $newTable) {
            $oldExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $oldTable));
            $newExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $newTable));

            if ($oldExists === $oldTable && $newExists !== $newTable) {
                // Rename legacy table to new table name
                $wpdb->query("ALTER TABLE `{$oldTable}` RENAME TO `{$newTable}`"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            }
        }

        // 2. Migrate legacy options
        $legacyOptions = $wpdb->get_results(
            "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE 'ranksavvy_%'"
        );

        if (!empty($legacyOptions)) {
            foreach ($legacyOptions as $opt) {
                $newOptionName = 'ameverywhere_' . substr($opt->option_name, strlen('ranksavvy_'));
                // Only migrate if new option is not already set
                if (get_option($newOptionName) === false) {
                    add_option($newOptionName, maybe_unserialize($opt->option_value), '', $opt->autoload);
                }
            }
        }

        // 3. Migrate legacy postmeta
        $wpdb->query(
            "UPDATE {$wpdb->postmeta} 
             SET meta_key = CONCAT('_ameverywhere_', SUBSTRING(meta_key, 12)) 
             WHERE meta_key LIKE '_ranksavvy_%'"
        );
    }
}
