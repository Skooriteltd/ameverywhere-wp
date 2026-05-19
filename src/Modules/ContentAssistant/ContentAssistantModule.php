<?php

namespace RankSavvy\Modules\ContentAssistant;

use RankSavvy\Core\Event\EventManager;

class ContentAssistantModule
{
    private EventManager $eventManager;

    public function __construct(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;
    }

    public function boot(): void
    {
        $this->eventManager->addAction('init', [$this, 'registerMetaFields']);
        $this->eventManager->addAction('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
    }

    public function registerMetaFields(): void
    {
        $metaKeys = [
            '_ranksavvy_meta_title',
            '_ranksavvy_meta_description',
            '_ranksavvy_is_news',
            '_ranksavvy_noindex',
            '_ranksavvy_og_title',
            '_ranksavvy_og_description',
            '_ranksavvy_og_image',
            '_ranksavvy_twitter_title',
            '_ranksavvy_focus_keyword'
        ];

        foreach (['post', 'page'] as $postType) {
            foreach ($metaKeys as $metaKey) {
                register_post_meta($postType, $metaKey, [
                    'show_in_rest' => true,
                    'single'       => true,
                    'type'         => 'string',
                    'auth_callback' => function() {
                        return current_user_can('edit_posts');
                    }
                ]);
            }
        }
    }

    public function enqueueEditorAssets(): void
    {
        $assetFile = RANKSAVVY_PLUGIN_DIR . 'build/editor.asset.php';
        
        if (!file_exists($assetFile)) {
            return;
        }

        $assets = require $assetFile;
        
        // Add specific dependencies needed for the sidebar
        $dependencies = array_merge(
            $assets['dependencies'],
            ['wp-plugins', 'wp-edit-post', 'wp-i18n', 'wp-components', 'wp-data', 'wp-core-data']
        );

        wp_enqueue_script(
            'ranksavvy-editor-js',
            RANKSAVVY_PLUGIN_URL . 'build/editor.js',
            $dependencies,
            $assets['version'],
            true
        );

        // Pass any necessary configuration to the frontend
        wp_localize_script('ranksavvy-editor-js', 'rankSavvyEditorConfig', [
            'apiUrl' => esc_url_raw(rest_url('ranksavvy/v1')),
            'nonce'  => wp_create_nonce('wp_rest')
        ]);
    }
}
