<?php

namespace AmEveryWhere\Modules\Analytics;

/**
 * MultisiteNetworkSeo
 *
 * BL-035: WordPress Multisite support — network-level SEO settings
 * with per-site overrides. Network admins manage defaults; site admins
 * can override them.
 */
class MultisiteNetworkSeo
{
    private const NETWORK_OPTION = 'ameverywhere_network_seo_settings';

    public function register(): void
    {
        if (!is_multisite()) {
            return;
        }

        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('network_admin_menu', [$this, 'addNetworkMenu']);
        add_filter('ameverywhere_get_option', [$this, 'maybeUseNetworkDefault'], 10, 2);
    }

    public function registerRoutes(): void
    {
        $networkAdminCap = fn() => current_user_can('manage_network_options');
        $siteAdminCap    = fn() => current_user_can('manage_options');

        // Network defaults (super admin only)
        register_rest_route('ameverywhere/v1', '/network/settings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getNetworkSettings'],
                'permission_callback' => $networkAdminCap,
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'saveNetworkSettings'],
                'permission_callback' => $networkAdminCap,
            ],
        ]);

        // Per-site override status
        register_rest_route('ameverywhere/v1', '/network/site-overrides', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getSiteOverrides'],
            'permission_callback' => $siteAdminCap,
        ]);

        // List all network sites with basic SEO health
        register_rest_route('ameverywhere/v1', '/network/sites', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getNetworkSites'],
            'permission_callback' => $networkAdminCap,
        ]);
    }

    public function getNetworkSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!is_multisite()) {
            return new \WP_Error('not_multisite', 'Not a multisite network.', ['status' => 400]);
        }

        $settings = get_site_option(self::NETWORK_OPTION, $this->getDefaults());
        return rest_ensure_response($settings);
    }

    public function saveNetworkSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!is_multisite()) {
            return new \WP_Error('not_multisite', 'Not a multisite network.', ['status' => 400]);
        }

        $params   = $request->get_json_params();
        $settings = [
            'default_schema_type'   => sanitize_text_field($params['default_schema_type'] ?? 'Article'),
            'force_ssl'             => (bool) ($params['force_ssl'] ?? true),
            'block_ai_bots'         => (bool) ($params['block_ai_bots'] ?? false),
            'allow_site_overrides'  => (bool) ($params['allow_site_overrides'] ?? true),
            'shared_indexnow_key'   => sanitize_text_field($params['shared_indexnow_key'] ?? ''),
            'network_robots_rules'  => sanitize_textarea_field($params['network_robots_rules'] ?? ''),
        ];

        update_site_option(self::NETWORK_OPTION, $settings);
        return rest_ensure_response(['success' => true, 'settings' => $settings]);
    }

    public function getSiteOverrides(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!is_multisite()) {
            return new \WP_Error('not_multisite', 'Not a multisite network.', ['status' => 400]);
        }

        $networkSettings = get_site_option(self::NETWORK_OPTION, $this->getDefaults());
        $siteOverrides   = get_option('ameverywhere_site_overrides', []);

        return rest_ensure_response([
            'network_settings' => $networkSettings,
            'site_overrides'   => $siteOverrides,
            'can_override'     => $networkSettings['allow_site_overrides'] ?? true,
        ]);
    }

    public function getNetworkSites(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!is_multisite()) {
            return new \WP_Error('not_multisite', 'Not a multisite network.', ['status' => 400]);
        }

        $sites  = get_sites(['number' => 100]);
        $result = [];

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            $result[] = [
                'blog_id'     => $site->blog_id,
                'domain'      => $site->domain . $site->path,
                'name'        => get_bloginfo('name'),
                'admin_email' => get_option('admin_email'),
                'post_count'  => wp_count_posts()->publish ?? 0,
            ];
            restore_current_blog();
        }

        return rest_ensure_response(['sites' => $result, 'total' => count($result)]);
    }

    public function addNetworkMenu(): void
    {
        add_menu_page(
            'AmEveryWhere Network',
            'AmEveryWhere',
            'manage_network_options',
            'ameverywhere-network',
            [$this, 'renderNetworkAdminPage'],
            'dashicons-chart-area',
            30
        );
    }

    public function renderNetworkAdminPage(): void
    {
        echo '<div id="ameverywhere-network-admin"><!-- React renders here --></div>';
    }

    /**
     * Filter: fall back to network defaults when a site option is not set.
     */
    public function maybeUseNetworkDefault($value, string $optionKey)
    {
        if ($value !== '' && $value !== null && $value !== false) {
            return $value;
        }

        $networkSettings = get_site_option(self::NETWORK_OPTION, []);
        $networkKey      = str_replace('ameverywhere_', '', $optionKey);

        return $networkSettings[$networkKey] ?? $value;
    }

    private function getDefaults(): array
    {
        return [
            'default_schema_type'  => 'Article',
            'force_ssl'            => true,
            'block_ai_bots'        => false,
            'allow_site_overrides' => true,
            'shared_indexnow_key'  => '',
            'network_robots_rules' => '',
        ];
    }
}
