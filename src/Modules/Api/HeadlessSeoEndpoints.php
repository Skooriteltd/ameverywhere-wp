<?php

namespace AmEveryWhere\Modules\Api;

/**
 * HeadlessSeoEndpoints
 *
 * Provides clean REST endpoints for headless WordPress deployments:
 *   GET  /ranksavvy/v1/seo/{post_id}  — all SEO meta for a post
 *   PUT  /ranksavvy/v1/seo/{post_id}  — update SEO meta
 *   GET  /ranksavvy/v1/seo/global     — site-wide SEO defaults
 *
 * BL-027
 */
class HeadlessSeoEndpoints
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // GET/PUT /seo/{post_id}
        register_rest_route('ameverywhere/v1', '/seo/(?P<post_id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getPostSeo'],
                'permission_callback' => '__return_true',  // public read — SEO data is public
                'args'                => ['post_id' => ['sanitize_callback' => 'absint']],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'updatePostSeo'],
                'permission_callback' => fn() => current_user_can('edit_posts'),
                'args'                => ['post_id' => ['sanitize_callback' => 'absint']],
            ],
        ]);

        // GET /seo/global
        register_rest_route('ameverywhere/v1', '/seo/global', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getGlobalSeo'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ── GET /seo/{post_id} ────────────────────────────────────────────────────

    public function getPostSeo(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        $post   = get_post($postId);

        if (!$post || $post->post_status !== 'publish') {
            return new \WP_Error('not_found', 'Post not found.', ['status' => 404]);
        }

        return rest_ensure_response($this->buildPostSeoPayload($postId));
    }

    private function buildPostSeoPayload(int $postId): array
    {
        $post   = get_post($postId);
        $schema = [];

        // Collect schema from wp_footer output (crude but reliable)
        ob_start();
        do_action('wp_head');
        $headHtml = ob_get_clean();

        // Extract JSON-LD blocks
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $headHtml, $m);
        foreach ($m[1] as $json) {
            $decoded = json_decode(trim($json), true);
            if ($decoded) {
                $schema[] = $decoded;
            }
        }

        return [
            'post_id'           => $postId,
            'post_type'         => $post->post_type,
            'url'               => get_permalink($postId),
            'meta_title'        => (string) get_post_meta($postId, '_ameverywhere_meta_title', true) ?: $post->post_title,
            'meta_description'  => (string) get_post_meta($postId, '_ameverywhere_meta_description', true),
            'focus_keyword'     => (string) get_post_meta($postId, '_ameverywhere_focus_keyword', true),
            'canonical_url'     => (string) get_post_meta($postId, '_ameverywhere_canonical_url', true) ?: get_permalink($postId),
            'robots'            => [
                'noindex'   => get_post_meta($postId, '_ameverywhere_noindex', true) === 'yes',
                'nofollow'  => get_post_meta($postId, '_ameverywhere_nofollow', true) === 'yes',
            ],
            'open_graph'        => [
                'title'       => (string) get_post_meta($postId, '_ameverywhere_og_title', true),
                'description' => (string) get_post_meta($postId, '_ameverywhere_og_description', true),
                'image'       => (string) get_post_meta($postId, '_ameverywhere_og_image', true),
            ],
            'twitter_card'      => [
                'title'       => (string) get_post_meta($postId, '_ameverywhere_twitter_title', true),
                'description' => (string) get_post_meta($postId, '_ameverywhere_twitter_description', true),
                'image'       => (string) get_post_meta($postId, '_ameverywhere_twitter_image', true),
            ],
            'schema_type'       => (string) get_post_meta($postId, '_ameverywhere_schema_type', true),
            'is_cornerstone'    => get_post_meta($postId, '_ameverywhere_is_cornerstone', true) === 'yes',
            'ai_generated'      => get_post_meta($postId, '_ameverywhere_ai_generated', true) === 'yes',
            'search_intent'     => (string) get_post_meta($postId, '_ameverywhere_search_intent', true),
            'structured_data'   => $schema,
            'modified'          => $post->post_modified,
        ];
    }

    // ── PUT /seo/{post_id} ────────────────────────────────────────────────────

    public function updatePostSeo(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        $post   = get_post($postId);

        if (!$post) {
            return new \WP_Error('not_found', 'Post not found.', ['status' => 404]);
        }

        if (!current_user_can('edit_post', $postId)) {
            return new \WP_Error('forbidden', 'You do not have permission to edit this post.', ['status' => 403]);
        }

        $params   = $request->get_json_params();
        $updated  = [];

        $stringFields = [
            'meta_title'        => '_ameverywhere_meta_title',
            'meta_description'  => '_ameverywhere_meta_description',
            'focus_keyword'     => '_ameverywhere_focus_keyword',
            'canonical_url'     => '_ameverywhere_canonical_url',
            'schema_type'       => '_ameverywhere_schema_type',
        ];

        foreach ($stringFields as $param => $metaKey) {
            if (array_key_exists($param, $params)) {
                update_post_meta($postId, $metaKey, sanitize_text_field($params[$param]));
                $updated[] = $param;
            }
        }

        if (isset($params['robots']['noindex'])) {
            update_post_meta($postId, '_ameverywhere_noindex', $params['robots']['noindex'] ? 'yes' : 'no');
            $updated[] = 'robots.noindex';
        }
        if (isset($params['robots']['nofollow'])) {
            update_post_meta($postId, '_ameverywhere_nofollow', $params['robots']['nofollow'] ? 'yes' : 'no');
            $updated[] = 'robots.nofollow';
        }

        return rest_ensure_response(['success' => true, 'updated_fields' => $updated, 'post_id' => $postId]);
    }

    // ── GET /seo/global ───────────────────────────────────────────────────────

    public function getGlobalSeo(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'site_name'          => get_bloginfo('name'),
            'site_description'   => get_bloginfo('description'),
            'site_url'           => site_url(),
            'home_url'           => home_url(),
            'admin_email'        => get_option('admin_email'),
            'language'           => get_bloginfo('language'),
            'charset'            => get_bloginfo('charset'),
            'sitemap_url'        => home_url('/sitemap.xml'),
            'robots_txt_url'     => home_url('/robots.txt'),
            'llms_txt_url'       => home_url('/llms.txt'),
            'block_ai_bots'      => get_option('ameverywhere_block_ai_bots', 'no') === 'yes',
            'ai_training_optout' => get_option('ameverywhere_ai_training_optout', 'no') === 'yes',
            'indexnow_key'       => get_option('ameverywhere_indexnow_key', ''),
            'schema_defaults'    => [
                'author'       => get_bloginfo('name'),
                'publisher'    => get_bloginfo('name'),
                'logo'         => get_site_icon_url(),
                'type_default' => get_option('ameverywhere_default_schema_type', 'Article'),
            ],
            'version'            => AMEVERYWHERE_VERSION ?? '1.0.0',
        ]);
    }
}
