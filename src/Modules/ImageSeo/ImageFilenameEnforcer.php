<?php

namespace AmEveryWhere\Modules\ImageSeo;

/**
 * ImageFilenameEnforcer
 *
 * Intercepts image uploads and rewrites the filename to an SEO-friendly
 * slug (lowercase, hyphens, no generic prefixes). Also provides a bulk
 * retroactive rename endpoint for the existing media library.
 *
 * BL-004
 */
class ImageFilenameEnforcer
{
    private const ENFORCE_OPTION = 'ameverywhere_filename_enforce';
    private const PREFIX_OPTION  = 'ameverywhere_filename_prefix';
    private const RENAMED_META   = '_ameverywhere_renamed';

    /** Generic prefixes that carry no SEO value */
    private const GENERIC_PREFIX_PATTERN = '/^(img|dsc|photo|pic|image|screenshot|capture|file|scan)([-_]?\d*)?[-_]?/i';

    public function register(): void
    {
        add_filter('wp_handle_upload_prefilter', [$this, 'enforceFilename']);
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    // ── Upload filter ─────────────────────────────────────────────────────────

    public function enforceFilename(array $file): array
    {
        if (get_option(self::ENFORCE_OPTION, 'yes') !== 'yes') {
            return $file;
        }

        $info = pathinfo($file['name']);
        $ext  = strtolower($info['extension'] ?? '');
        if (empty($ext)) {
            return $file;
        }

        $newName = $this->cleanFilename($info['filename']);

        if (empty($newName)) {
            $newName = 'image';
        }

        $prefix = sanitize_text_field(get_option(self::PREFIX_OPTION, ''));
        if (!empty($prefix)) {
            $newName = $prefix . '-' . $newName;
        }

        $file['name'] = $newName . '.' . $ext;

        return $file;
    }

    // ── Filename cleaner ──────────────────────────────────────────────────────

    public function cleanFilename(string $name): string
    {
        // Transliterate accented characters to ASCII
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            if ($converted !== false) {
                $name = $converted;
            }
        }

        $name = mb_strtolower($name);

        // Replace separators with hyphens
        $name = preg_replace('/[\s_\.]+/', '-', $name) ?? $name;

        // Remove anything that is not a-z, 0-9, or hyphen
        $name = preg_replace('/[^a-z0-9\-]/', '', $name) ?? $name;

        // Collapse multiple hyphens
        $name = preg_replace('/-+/', '-', $name) ?? $name;

        // Strip generic prefixes
        $name = preg_replace(self::GENERIC_PREFIX_PATTERN, '', $name) ?? $name;

        // Strip WP size suffixes like -300x200
        $name = preg_replace('/-\d+x\d+$/', '', $name) ?? $name;

        return trim($name, '-');
    }

    // ── REST routes ───────────────────────────────────────────────────────────

    public function registerRoutes(): void
    {
        register_rest_route('ameverywhere/v1', '/settings/filename-enforce', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSettings'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'saveSettings'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
        ]);

        register_rest_route('ameverywhere/v1', '/images/bulk-rename', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'bulkRename'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function getSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'enforce' => get_option(self::ENFORCE_OPTION, 'yes') === 'yes',
            'prefix'  => get_option(self::PREFIX_OPTION, ''),
        ]);
    }

    public function saveSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        if (isset($params['enforce'])) {
            update_option(self::ENFORCE_OPTION, $params['enforce'] ? 'yes' : 'no');
        }

        if (isset($params['prefix'])) {
            // Prefix: alphanumeric + hyphens only, max 30 chars
            $prefix = preg_replace('/[^a-z0-9\-]/', '', strtolower(sanitize_text_field($params['prefix'])));
            update_option(self::PREFIX_OPTION, substr((string) $prefix, 0, 30));
        }

        return rest_ensure_response(['success' => true]);
    }

    public function bulkRename(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $params    = $request->get_json_params();
        $ids       = array_map('absint', $params['ids'] ?? []);
        $batchSize = min(20, max(1, (int) ($params['batch_size'] ?? 10)));

        // If no IDs provided, fetch unprocessed images
        if (empty($ids)) {
            $query = new \WP_Query([
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                'posts_per_page' => $batchSize,
                'meta_query'     => [
                    ['key' => self::RENAMED_META, 'compare' => 'NOT EXISTS'],
                ],
            ]);
            $ids = wp_list_pluck($query->posts, 'ID');
        }

        $renamed   = 0;
        $skipped   = 0;
        $failed    = 0;

        foreach ($ids as $id) {
            $filePath = get_attached_file($id);
            if (!$filePath || !file_exists($filePath)) {
                $failed++;
                continue;
            }

            $info    = pathinfo($filePath);
            $newBase = $this->cleanFilename($info['filename']);

            if (empty($newBase)) {
                $skipped++;
                continue;
            }

            $ext     = strtolower($info['extension'] ?? '');
            $newName = $newBase . '.' . $ext;
            $newPath = $info['dirname'] . '/' . $newName;

            if ($newPath === $filePath) {
                update_post_meta($id, self::RENAMED_META, 'yes');
                $skipped++;
                continue;
            }

            // Ensure unique target filename
            $counter = 2;
            $testPath = $newPath;
            while (file_exists($testPath)) {
                $testPath = $info['dirname'] . '/' . $newBase . '-' . $counter . '.' . $ext;
                $testName = $newBase . '-' . $counter . '.' . $ext;
                $counter++;
            }
            $newPath = $testPath;
            $newName = basename($newPath);

            if (!@rename($filePath, $newPath)) {
                $failed++;
                continue;
            }

            // Update WordPress records
            update_attached_file($id, $newPath);

            $meta = wp_get_attachment_metadata($id);
            if (is_array($meta)) {
                $meta['file'] = _wp_relative_upload_path($newPath);
                wp_update_attachment_metadata($id, $meta);
            }

            // Update post guid
            $oldGuid = get_the_guid($id);
            $newGuid = str_replace(basename($filePath), $newName, $oldGuid);
            $wpdb->update($wpdb->posts, ['guid' => $newGuid], ['ID' => $id]);

            update_post_meta($id, self::RENAMED_META, 'yes');
            $renamed++;
        }

        // Count remaining unprocessed
        $remaining = (new \WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'posts_per_page' => 1,
            'meta_query'     => [
                ['key' => self::RENAMED_META, 'compare' => 'NOT EXISTS'],
            ],
        ]))->found_posts;

        return rest_ensure_response([
            'success'   => true,
            'renamed'   => $renamed,
            'skipped'   => $skipped,
            'failed'    => $failed,
            'remaining' => $remaining,
        ]);
    }
}
