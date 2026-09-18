<?php

namespace AmEveryWhere\Modules\Admin;

class AdminMenu
{
    public function registerMenu(): void
    {
        add_menu_page(
            'AmEveryWhere SEO',
            'AmEveryWhere',
            'manage_options',
            'ameverywhere',
            [$this, 'renderAdminPage'],
            'dashicons-chart-area',
            85
        );
    }

    public function enqueueAssets(string $hook): void
    {
        // Only load assets on our plugin page (support both new and legacy hooks)
        if ($hook !== 'toplevel_page_ameverywhere' && $hook !== 'toplevel_page_ranksavvy') {
            return;
        }

        $assetFile = AMEVERYWHERE_PLUGIN_DIR . 'build/index.asset.php';
        
        if (file_exists($assetFile)) {
            $assets = require $assetFile;
            wp_enqueue_script(
                'ameverywhere-admin-js',
                AMEVERYWHERE_PLUGIN_URL . 'build/index.js',
                $assets['dependencies'],
                $assets['version'],
                true
            );

            $adminConfig = [
                'apiUrl'        => esc_url_raw(rest_url('ameverywhere/v1')),
                'nonce'         => wp_create_nonce('wp_rest'),
                'setupComplete' => get_option('ameverywhere_setup_complete') ? '1' : '0',
                'isMultisite'   => is_multisite() ? '1' : '0',
            ];

            // Localize both new and legacy config variables for backward compatibility
            wp_localize_script('ameverywhere-admin-js', 'amEveryWhereAdminConfig', $adminConfig);
            wp_localize_script('ameverywhere-admin-js', 'rankSavvyAdminConfig', $adminConfig);
        }

        wp_enqueue_style(
            'ameverywhere-admin-css',
            AMEVERYWHERE_PLUGIN_URL . 'build/index.css',
            [],
            AMEVERYWHERE_VERSION
        );
        wp_style_add_data('ameverywhere-admin-css', 'rtl', 'replace');
    }

    public function renderAdminPage(): void
    {
        echo '<div class="wrap"><div id="ameverywhere-admin-app"></div></div>';
    }
}
