<?php

namespace RankSavvy\Modules\Ai;

/**
 * LlmsTxtGenerator: Serves a machine-readable /llms.txt file for LLM ingestion.
 *
 * The llms.txt standard (proposed by Answer.AI) gives AI assistants
 * a structured overview of the site's content and permission guidelines.
 *
 * @see https://llmstxt.org/
 */
class LlmsTxtGenerator
{
    public function boot(): void
    {
        add_action('init', [$this, 'registerRewriteRule']);
        add_action('template_redirect', [$this, 'serveFile']);
        add_filter('query_vars', [$this, 'registerQueryVar']);
    }

    public function registerRewriteRule(): void
    {
        add_rewrite_rule('^llms\.txt$', 'index.php?ranksavvy_llms_txt=1', 'top');
    }

    public function registerQueryVar(array $vars): array
    {
        $vars[] = 'ranksavvy_llms_txt';
        return $vars;
    }

    public function serveFile(): void
    {
        if (!get_query_var('ranksavvy_llms_txt')) {
            return;
        }

        $config = get_option('ranksavvy_llms_txt_config', []);

        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');

        echo $this->buildContent($config);
        exit;
    }

    public function buildContent(array $config): string
    {
        $siteName    = get_bloginfo('name');
        $siteUrl     = get_site_url();
        $description = $config['description'] ?? get_bloginfo('description');
        $allowIndex  = $config['allow_index'] ?? true;
        $allowTrain  = $config['allow_training'] ?? false;
        $extraNotes  = $config['extra_notes'] ?? '';
        $contactUrl  = $config['contact_url'] ?? get_option('admin_email');

        $output  = "# {$siteName}\n\n";
        $output .= "> {$description}\n\n";
        $output .= "## Site\n\n";
        $output .= "- URL: {$siteUrl}\n";
        $output .= "- Contact: {$contactUrl}\n\n";

        $output .= "## Permissions\n\n";
        $output .= '- Indexing: ' . ($allowIndex ? 'Allowed' : 'Not Allowed') . "\n";
        $output .= '- Training data use: ' . ($allowTrain ? 'Allowed' : 'Not Allowed') . "\n\n";

        // Append schema map link for LLM ingestion
        $output .= "## Structured Data\n\n";
        $output .= "- Schema Map: {$siteUrl}/wp-json/ranksavvy/v1/schemamap\n\n";

        // Top content pages
        $output .= "## Key Pages\n\n";
        $topPosts = get_posts([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ]);

        foreach ($topPosts as $post) {
            $title = get_the_title($post->ID);
            $url   = get_permalink($post->ID);
            $output .= "- [{$title}]({$url})\n";
        }

        if (!empty($extraNotes)) {
            $output .= "\n## Notes\n\n";
            $output .= $extraNotes . "\n";
        }

        return $output;
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('ranksavvy/v1', '/llms-txt/config', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getConfig'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'saveConfig'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
        ]);
    }

    public function getConfig(): \WP_REST_Response
    {
        $config = get_option('ranksavvy_llms_txt_config', [
            'description'    => get_bloginfo('description'),
            'allow_index'    => true,
            'allow_training' => false,
            'contact_url'    => get_option('admin_email'),
            'extra_notes'    => '',
        ]);

        // Return preview too
        $config['preview'] = $this->buildContent($config);

        return rest_ensure_response($config);
    }

    public function saveConfig(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        $config = [
            'description'    => sanitize_text_field($params['description'] ?? ''),
            'allow_index'    => (bool) ($params['allow_index'] ?? true),
            'allow_training' => (bool) ($params['allow_training'] ?? false),
            'contact_url'    => sanitize_text_field($params['contact_url'] ?? ''),
            'extra_notes'    => sanitize_textarea_field($params['extra_notes'] ?? ''),
        ];

        update_option('ranksavvy_llms_txt_config', $config);

        // Flush rewrite rules so /llms.txt is immediately available
        flush_rewrite_rules();

        $config['preview'] = $this->buildContent($config);

        return rest_ensure_response(['success' => true, 'config' => $config]);
    }
}
