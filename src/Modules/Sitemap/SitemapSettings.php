<?php

namespace AmEveryWhere\Modules\Sitemap;

/**
 * Handles database operations for AmEveryWhere sitemap settings using the custom table.
 * Fallbacks to options and handles transparent on-demand migrations.
 */
class SitemapSettings
{
    private static string $table = 'ameverywhere_sitemap_settings';

    /**
     * Retrieve a sitemap configuration setting.
     */
    public static function get(string $key, $default = null)
    {
        global $wpdb;
        $tableName = $wpdb->prefix . self::$table;

        // Check if custom table exists; if not, fallback to options
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") !== $tableName) {
            return get_option('ameverywhere_' . $key, $default);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT setting_value FROM $tableName WHERE setting_key = %s", $key));
        if ($row !== null) {
            return maybe_unserialize($row->setting_value);
        }

        // Check fallback to option table for backwards compatibility
        $optVal = get_option('ameverywhere_' . $key, null);
        if ($optVal !== null) {
            // One-time self-healing migration
            self::set($key, $optVal);
            delete_option('ameverywhere_' . $key);
            return $optVal;
        }

        return $default;
    }

    /**
     * Save a sitemap configuration setting.
     */
    public static function set(string $key, $value): void
    {
        global $wpdb;
        $tableName = $wpdb->prefix . self::$table;

        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") !== $tableName) {
            update_option('ameverywhere_' . $key, $value);
            return;
        }

        $serializedValue = maybe_serialize($value);

        $wpdb->replace($tableName, [
            'setting_key'   => $key,
            'setting_value' => $serializedValue
        ]);
    }
}
