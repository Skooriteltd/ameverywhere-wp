<?php

namespace RankSavvy\Modules\Admin;

class AdminMenu
{
    public function registerMenu(): void
    {
        add_menu_page(
            'RankSavvy SEO',
            'RankSavvy',
            'manage_options',
            'ranksavvy',
            [$this, 'renderAdminPage'],
            'dashicons-chart-area', // Use a suitable dashicon
            85
        );
    }

    public function enqueueAssets(string $hook): void
    {
        // Only load assets on our plugin page
        if ($hook !== 'toplevel_page_ranksavvy') {
            return;
        }

        $assetFile = RANKSAVVY_PLUGIN_DIR . 'build/index.asset.php';
        
        if (file_exists($assetFile)) {
            $assets = require $assetFile;
            wp_enqueue_script(
                'ranksavvy-admin-js',
                RANKSAVVY_PLUGIN_URL . 'build/index.js',
                $assets['dependencies'],
                $assets['version'],
                true
            );

            wp_localize_script('ranksavvy-admin-js', 'rankSavvyAdminConfig', [
                'apiUrl' => esc_url_raw(rest_url('ranksavvy/v1')),
                'nonce'  => wp_create_nonce('wp_rest')
            ]);
        }

        wp_enqueue_style(
            'ranksavvy-admin-css',
            RANKSAVVY_PLUGIN_URL . 'build/index.css',
            [],
            RANKSAVVY_VERSION
        );
    }

    public function renderAdminPage(): void
    {
        echo '<div class="wrap"><div id="ranksavvy-admin-app"></div></div>';
    }
}
