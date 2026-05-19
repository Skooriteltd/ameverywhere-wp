<?php

namespace RankSavvy\Modules\ImageSeo;

use RankSavvy\Core\Event\EventManager;

/**
 * Automatically sets image alt text and title attributes based on configurable templates.
 * Also redirects attachment pages to the parent post to prevent thin content.
 */
class ImageSeoModule
{
    private EventManager $eventManager;

    public function __construct(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;
    }

    public function boot(): void
    {
        // Auto-set alt text on media upload
        $this->eventManager->addAction('add_attachment', [$this, 'autoSetAltText']);

        // Fix missing alt tags in post content on save
        $this->eventManager->addFilter('wp_insert_post_data', [$this, 'fixMissingAltTags'], 10, 2);

        // Redirect attachment pages to parent post
        $this->eventManager->addAction('template_redirect', [$this, 'redirectAttachmentPages']);
    }

    /**
     * Automatically set alt text and title when a new image is uploaded.
     * Uses the filename (cleaned up) as the default alt text.
     */
    public function autoSetAltText(int $attachmentId): void
    {
        $attachment = get_post($attachmentId);
        if (!$attachment || !wp_attachment_is_image($attachmentId)) {
            return;
        }

        // Only set if alt text is empty
        $existingAlt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
        if (!empty($existingAlt)) {
            return;
        }

        // Clean up filename to create readable alt text
        $filename = pathinfo(get_attached_file($attachmentId), PATHINFO_FILENAME);
        $altText = $this->cleanFilename($filename);

        if (!empty($altText)) {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $altText);

            // Also set the title if it's just the raw filename
            if ($attachment->post_title === $filename) {
                wp_update_post([
                    'ID'         => $attachmentId,
                    'post_title' => $altText,
                ]);
            }
        }
    }

    /**
     * Scan post content for images missing alt attributes and add them
     * based on the attachment's alt text or the post title.
     */
    public function fixMissingAltTags(array $data, array $postarr): array
    {
        if (empty($data['post_content'])) {
            return $data;
        }

        $content = $data['post_content'];
        $postTitle = $data['post_title'] ?? '';

        // Find <img> tags without alt or with empty alt
        $content = preg_replace_callback(
            '/<img\b([^>]*?)>/i',
            function ($matches) use ($postTitle) {
                $tag = $matches[0];
                $attrs = $matches[1];

                // Check if alt already exists and is non-empty
                if (preg_match('/\balt\s*=\s*"([^"]+)"/i', $attrs)) {
                    return $tag; // Already has alt text
                }

                // Try to get alt from attachment
                $altText = '';
                if (preg_match('/wp-image-(\d+)/i', $attrs, $idMatch)) {
                    $attachmentId = (int) $idMatch[1];
                    $altText = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
                }

                // Fallback to post title
                if (empty($altText)) {
                    $altText = $postTitle;
                }

                if (empty($altText)) {
                    return $tag;
                }

                $altAttr = 'alt="' . esc_attr($altText) . '"';

                // Replace empty alt or add alt attribute
                if (preg_match('/\balt\s*=\s*""/i', $attrs)) {
                    $tag = preg_replace('/\balt\s*=\s*""/i', $altAttr, $tag);
                } else {
                    $tag = str_replace('<img', '<img ' . $altAttr, $tag);
                }

                return $tag;
            },
            $content
        );

        $data['post_content'] = $content;
        return $data;
    }

    /**
     * Redirect attachment pages to their parent post to prevent thin content issues.
     */
    public function redirectAttachmentPages(): void
    {
        if (!is_attachment()) {
            return;
        }

        global $post;

        if ($post && $post->post_parent > 0) {
            $parentUrl = get_permalink($post->post_parent);
        } else {
            $parentUrl = home_url('/');
        }

        wp_redirect($parentUrl, 301);
        exit;
    }

    /**
     * Clean a filename into readable alt text.
     * "my-awesome_image-2024" → "My Awesome Image 2024"
     */
    private function cleanFilename(string $filename): string
    {
        // Remove common size suffixes like -300x200
        $cleaned = preg_replace('/-\d+x\d+$/', '', $filename);

        // Replace separators with spaces
        $cleaned = str_replace(['-', '_', '.'], ' ', $cleaned);

        // Remove extra spaces and capitalize
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = ucwords(trim($cleaned));

        return $cleaned;
    }
}
