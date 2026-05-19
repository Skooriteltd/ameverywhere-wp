<?php

namespace RankSavvy\Modules\TechnicalSeo;

/**
 * Provides a virtual robots.txt editor.
 * Stores custom rules in wp_options and intercepts WordPress's default robots.txt output.
 */
class RobotsTxtEditor
{
    private const OPTION_KEY = 'ranksavvy_robots_txt';

    /**
     * Register hooks.
     */
    public function register(): void
    {
        add_filter('robots_txt', [$this, 'filterRobotsTxt'], 999, 2);
    }

    /**
     * Register REST routes for the robots.txt editor.
     */
    public function registerRoutes(): void
    {
        register_rest_route('ranksavvy/v1', '/robots-txt', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getRobotsTxt'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'updateRobotsTxt'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ],
        ]);
    }

    /**
     * Get the current robots.txt content.
     */
    public function getRobotsTxt(\WP_REST_Request $request): \WP_REST_Response
    {
        $custom = get_option(self::OPTION_KEY, '');

        if (empty($custom)) {
            // Generate the WordPress default
            $custom = $this->getDefaultRobotsTxt();
        }

        return rest_ensure_response(['content' => $custom]);
    }

    /**
     * Update the robots.txt content.
     */
    public function updateRobotsTxt(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $content = $params['content'] ?? '';

        // Sanitize: only allow safe characters (letters, numbers, basic punctuation, newlines)
        $content = sanitize_textarea_field($content);

        update_option(self::OPTION_KEY, $content);

        return rest_ensure_response(['success' => true]);
    }

    /**
     * Filter the robots.txt output if custom rules are saved.
     */
    public function filterRobotsTxt(string $output, bool $public): string
    {
        $custom = get_option(self::OPTION_KEY, '');

        if (!empty($custom)) {
            return $custom;
        }

        return $output;
    }

    /**
     * Generate a sensible default robots.txt.
     */
    private function getDefaultRobotsTxt(): string
    {
        $siteUrl = site_url('/');

        return implode("\n", [
            'User-agent: *',
            'Disallow: /wp-admin/',
            'Allow: /wp-admin/admin-ajax.php',
            '',
            'Sitemap: ' . $siteUrl . 'sitemap.xml',
            'Sitemap: ' . $siteUrl . 'news-sitemap.xml',
            '',
        ]);
    }
}
