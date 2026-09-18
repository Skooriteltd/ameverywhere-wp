<?php

namespace AmEveryWhere\Modules\ImageSeo;

/**
 * ImageCompressor: Auto-compresses uploaded images and generates WebP variants.
 *
 * Hooks into wp_handle_upload to process new uploads immediately.
 * Provides a REST endpoint for bulk-processing the existing library.
 * Uses Imagick when available, falls back to GD.
 */
class ImageCompressor
{
    private const OPTION_KEY         = 'ameverywhere_image_compression';
    private const PROCESSED_META_KEY = '_ameverywhere_compressed';

    public function boot(): void
    {
        $config = $this->getConfig();
        if (!$config['enabled']) {
            return;
        }

        // Hook into the upload pipeline after WordPress has moved and validated the file
        add_filter('wp_handle_upload', [$this, 'compressOnUpload'], 10, 2);
        add_action('rest_api_init',    [$this, 'registerRoutes']);
    }

    /**
     * Called after a file is uploaded. Compress in-place and optionally create WebP.
     *
     * @param array{file:string,url:string,type:string} $upload
     * @return array{file:string,url:string,type:string}
     */
    public function compressOnUpload(array $upload, string $context): array
    {
        if ($context !== 'upload') {
            return $upload;
        }

        $mime = $upload['type'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return $upload;
        }

        $config = $this->getConfig();
        $this->processFile($upload['file'], $mime, $config);

        return $upload;
    }

    /**
     * Compress and optionally convert a single file to WebP.
     * Returns true on success, false on failure.
     */
    public function processFile(string $filePath, string $mime, array $config): bool
    {
        if (!file_exists($filePath) || !is_writable($filePath)) {
            return false;
        }

        $quality = (int) ($config['quality'] ?? 82);
        $webp    = (bool) ($config['webp'] ?? true);

        // Prefer Imagick for better compression quality
        if (extension_loaded('imagick')) {
            return $this->processWithImagick($filePath, $mime, $quality, $webp);
        }

        if (extension_loaded('gd')) {
            return $this->processWithGd($filePath, $mime, $quality, $webp);
        }

        return false;
    }

    // ── Imagick implementation ────────────────────────────────────────────────

    private function processWithImagick(string $path, string $mime, int $quality, bool $webp): bool
    {
        try {
            $imagick = new \Imagick($path);
            $imagick->stripImage(); // Remove EXIF/metadata

            // Auto-orient based on EXIF data
            $imagick->autoOrient();

            if ($mime === 'image/jpeg') {
                $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
                $imagick->setImageCompressionQuality($quality);
                $imagick->setInterlaceScheme(\Imagick::INTERLACE_JPEG); // Progressive
            } elseif ($mime === 'image/png') {
                $imagick->setImageFormat('png');
                // PNG compression 0-9, quality 0-100 mapped → 0-9
                $imagick->setImageCompressionQuality(min(9, (int) floor((100 - $quality) / 11)));
            }

            $imagick->writeImage($path);

            // Generate WebP variant alongside the original
            if ($webp && in_array($mime, ['image/jpeg', 'image/png'], true)) {
                $webpPath = preg_replace('/\.[a-z]+$/i', '.webp', $path);
                $clone    = clone $imagick;
                $clone->setImageFormat('webp');
                $clone->setImageCompressionQuality($quality);
                $clone->writeImage((string) $webpPath);
                $clone->destroy();
            }

            $imagick->destroy();
            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    // ── GD fallback ───────────────────────────────────────────────────────────

    private function processWithGd(string $path, string $mime, int $quality, bool $webp): bool
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/gif'  => @imagecreatefromgif($path),
            default      => false,
        };

        if (!$image) {
            return false;
        }

        $result = match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, $quality),
            'image/png'  => imagepng($image, $path, min(9, (int) floor((100 - $quality) / 11))),
            'image/gif'  => imagegif($image, $path),
            default      => false,
        };

        // WebP conversion via GD (PHP 7.0+)
        if ($webp && $result && function_exists('imagewebp') && in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $webpPath = preg_replace('/\.[a-z]+$/i', '.webp', $path);
            imagewebp($image, (string) $webpPath, $quality);
        }

        imagedestroy($image);
        return (bool) $result;
    }

    // ── REST API ─────────────────────────────────────────────────────────────

    public function registerRoutes(): void
    {
        // Settings CRUD
        register_rest_route('ameverywhere/v1', '/settings/image-compression', [
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

        // Bulk compress endpoint — processes library in batches
        register_rest_route('ameverywhere/v1', '/images/compress-bulk', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'bulkCompress'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        // Stats endpoint — total images, compressed count, saved bytes estimate
        register_rest_route('ameverywhere/v1', '/images/compression-stats', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getStats'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function getSettings(): \WP_REST_Response
    {
        return rest_ensure_response($this->getConfig());
    }

    public function saveSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $config = [
            'enabled'  => (bool) ($params['enabled']  ?? true),
            'quality'  => min(100, max(40, (int) ($params['quality']  ?? 82))),
            'webp'     => (bool) ($params['webp']     ?? true),
            'preserve' => (bool) ($params['preserve'] ?? true), // Keep originals
        ];
        update_option(self::OPTION_KEY, $config);
        return rest_ensure_response(['success' => true, 'config' => $config]);
    }

    /**
     * Bulk compress unprocessed images. Processes up to $batchSize per request
     * so the admin UI can call this repeatedly (with progress tracking).
     */
    public function bulkCompress(\WP_REST_Request $request): \WP_REST_Response
    {
        $params    = $request->get_json_params();
        $batchSize = min(50, max(1, (int) ($params['batch_size'] ?? 20)));
        $config    = $this->getConfig();

        $query = new \WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif'],
            'posts_per_page' => $batchSize,
            'meta_query'     => [
                ['key' => self::PROCESSED_META_KEY, 'compare' => 'NOT EXISTS'],
            ],
        ]);

        $processed = 0;
        $failed    = 0;

        foreach ($query->posts as $post) {
            $file = get_attached_file($post->ID);
            if (!$file || !file_exists($file)) {
                continue;
            }

            $mime = get_post_mime_type($post->ID);
            if (!$mime) {
                continue;
            }

            // Optionally back up original before compression
            if ($config['preserve'] && !file_exists($file . '.original')) {
                @copy($file, $file . '.original');
            }

            $ok = $this->processFile($file, $mime, $config);
            update_post_meta($post->ID, self::PROCESSED_META_KEY, $ok ? 'yes' : 'failed');

            if ($ok) {
                $processed++;
                // Regenerate thumbnail sizes using the newly compressed file
                wp_update_attachment_metadata($post->ID, wp_generate_attachment_metadata($post->ID, $file));
            } else {
                $failed++;
            }
        }

        // Count remaining unprocessed
        $remaining = (new \WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif'],
            'posts_per_page' => 1,
            'meta_query'     => [['key' => self::PROCESSED_META_KEY, 'compare' => 'NOT EXISTS']],
        ]))->found_posts;

        return rest_ensure_response([
            'success'    => true,
            'processed'  => $processed,
            'failed'     => $failed,
            'remaining'  => $remaining,
            'done'       => $remaining === 0,
        ]);
    }

    public function getStats(): \WP_REST_Response
    {
        $total = (new \WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif'],
            'posts_per_page' => 1,
        ]))->found_posts;

        $compressed = (new \WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif'],
            'posts_per_page' => 1,
            'meta_query'     => [['key' => self::PROCESSED_META_KEY, 'value' => 'yes']],
        ]))->found_posts;

        return rest_ensure_response([
            'total'            => $total,
            'compressed'       => $compressed,
            'uncompressed'     => max(0, $total - $compressed),
            'engine'           => extension_loaded('imagick') ? 'imagick' : (extension_loaded('gd') ? 'gd' : 'none'),
            'webp_supported'   => extension_loaded('imagick') || function_exists('imagewebp'),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getConfig(): array
    {
        $defaults = ['enabled' => true, 'quality' => 82, 'webp' => true, 'preserve' => true];
        $saved    = get_option(self::OPTION_KEY, []);
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }
}
