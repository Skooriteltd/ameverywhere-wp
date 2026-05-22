<?php

namespace RankSavvy\Modules\TechnicalSeo;

/**
 * Provides a virtual robots.txt editor with optional automatic AI crawler blocking.
 * Stores custom rules in wp_options and intercepts WordPress's default robots.txt output.
 */
class RobotsTxtEditor
{
    private const OPTION_KEY = 'ranksavvy_robots_txt';

    // List of major AI scraper/crawler User-Agents
    private const AI_BOTS = [
        'GPTBot',
        'ChatGPT-User',
        'CCBot',
        'Google-Extended',
        'Anthropic-AI',
        'Claude-Web',
        'ClaudeBot',
        'cohere-ai',
        'Omgilibot',
        'Omgili',
        'PerplexityBot',
        'YouBot'
    ];

    /**
     * Register hooks.
     */
    public function register(): void
    {
        add_filter('robots_txt', [$this, 'filterRobotsTxt'], 999, 2);
        
        // Also perform proactive HTTP-level block if enabled
        add_action('init', [$this, 'proactiveBlockAiBots']);
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

        return rest_ensure_response([
            'content'       => $custom,
            'block_ai_bots' => get_option('ranksavvy_block_ai_bots', 'no') === 'yes',
        ]);
    }

    /**
     * Update the robots.txt content.
     */
    public function updateRobotsTxt(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        
        if (isset($params['content'])) {
            $content = sanitize_textarea_field($params['content']);
            update_option(self::OPTION_KEY, $content);
        }

        if (isset($params['block_ai_bots'])) {
            update_option('ranksavvy_block_ai_bots', $params['block_ai_bots'] ? 'yes' : 'no');
        }

        return rest_ensure_response(['success' => true]);
    }

    /**
     * Filter the robots.txt output if custom rules are saved.
     */
    public function filterRobotsTxt(string $output, bool $public): string
    {
        $custom = get_option(self::OPTION_KEY, '');

        if (empty($custom)) {
            $custom = $this->getDefaultRobotsTxt();
        }

        // If block AI bots is enabled, append block directives to robots.txt dynamically
        if (get_option('ranksavvy_block_ai_bots', 'no') === 'yes') {
            $custom .= "\n# Block AI Crawlers and LLM Bots (RankSavvy AI Crawler Manager)\n";
            foreach (self::AI_BOTS as $bot) {
                $custom .= "User-agent: " . $bot . "\nDisallow: /\n";
            }
            $custom .= "\n";
        }

        return $custom;
    }

    /**
     * Proactively block AI bots at the HTTP level if configured.
     */
    public function proactiveBlockAiBots(): void
    {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (get_option('ranksavvy_block_ai_bots', 'no') !== 'yes') {
            return;
        }

        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        if (empty($userAgent)) {
            return;
        }

        foreach (self::AI_BOTS as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                status_header(403);
                header('Content-Type: text/plain; charset=utf-8');
                echo "403 Forbidden: AI Crawlers and Scrapers are blocked from accessing this site.";
                exit;
            }
        }
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
