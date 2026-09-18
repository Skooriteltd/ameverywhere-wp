<?php

namespace AmEveryWhere\Modules\Admin;

/**
 * PageBuilderSeoWrappers
 *
 * BL-036: Native SEO meta integration for Elementor, Divi, and WPBakery.
 * Hooks into each builder's save/update cycle and applies AmEveryWhere
 * meta fields to the underlying WordPress post.
 */
class PageBuilderSeoWrappers
{
    public function register(): void
    {
        // Elementor
        if (did_action('elementor/loaded') || class_exists('\Elementor\Plugin')) {
            add_action('elementor/editor/after_save', [$this, 'onElementorSave'], 10, 2);
            add_action('elementor/widgets/register', [$this, 'registerElementorWidget']);
        }

        // Divi
        if (defined('ET_BUILDER_VERSION') || function_exists('et_builder_should_load_framework')) {
            add_filter('et_save_post', [$this, 'onDiviSave'], 10, 1);
        }

        // WPBakery (VC)
        if (defined('WPB_VC_VERSION') || class_exists('WPBMap')) {
            add_action('vc_after_init', [$this, 'registerVcParams']);
        }

        // REST endpoint for builder meta sync
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    // ── Elementor ─────────────────────────────────────────────────────────────

    public function onElementorSave(int $postId, array $editorData): void
    {
        // Elementor saves post via its own mechanism; keep AmEveryWhere meta in sync
        $this->syncBuilderMeta($postId);
    }

    public function registerElementorWidget(): void
    {
        // Placeholder — in production this would register a custom Elementor
        // widget panel for AmEveryWhere SEO settings via Elementor's widget system.
        // Requires Elementor to be loaded.
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }
        // Widget registration would go here
    }

    // ── Divi ──────────────────────────────────────────────────────────────────

    public function onDiviSave(int $postId): void
    {
        $this->syncBuilderMeta($postId);
    }

    // ── WPBakery ──────────────────────────────────────────────────────────────

    public function registerVcParams(): void
    {
        // Register AmEveryWhere SEO params in WPBakery's backend editor
        if (!function_exists('vc_add_params')) {
            return;
        }
        // Params would be registered here per-element
    }

    // ── REST ──────────────────────────────────────────────────────────────────

    public function registerRoutes(): void
    {
        register_rest_route('ameverywhere/v1', '/builder/sync/(?P<post_id>\d+)', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'syncEndpoint'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('ameverywhere/v1', '/builder/detect', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'detectBuilders'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function syncEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        if (!current_user_can('edit_post', $postId)) {
            return new \WP_Error('forbidden', 'You cannot edit this post.', ['status' => 403]);
        }

        $params = $request->get_json_params();

        $fieldMap = [
            'meta_title'       => '_ameverywhere_meta_title',
            'meta_description' => '_ameverywhere_meta_description',
            'focus_keyword'    => '_ameverywhere_focus_keyword',
            'canonical_url'    => '_ameverywhere_canonical_url',
        ];

        $updated = [];
        foreach ($fieldMap as $param => $metaKey) {
            if (array_key_exists($param, $params)) {
                update_post_meta($postId, $metaKey, sanitize_text_field($params[$param]));
                $updated[] = $metaKey;
            }
        }

        return rest_ensure_response(['success' => true, 'post_id' => $postId, 'updated' => $updated]);
    }

    public function detectBuilders(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'elementor'  => did_action('elementor/loaded') || class_exists('\Elementor\Plugin'),
            'divi'       => defined('ET_BUILDER_VERSION') || function_exists('et_builder_should_load_framework'),
            'wpbakery'   => defined('WPB_VC_VERSION') || class_exists('WPBMap'),
            'gutenberg'  => function_exists('register_block_type'),
        ]);
    }

    // ── Shared helper ─────────────────────────────────────────────────────────

    private function syncBuilderMeta(int $postId): void
    {
        if (!$postId || !get_post($postId)) {
            return;
        }
        // Fire the existing post save hook so all AmEveryWhere modules stay in sync
        do_action('ameverywhere_post_saved', $postId);
    }
}
