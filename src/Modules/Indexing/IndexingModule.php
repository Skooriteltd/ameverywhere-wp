<?php

namespace RankSavvy\Modules\Indexing;

use RankSavvy\Core\Event\EventManager;
use RankSavvy\Core\Queue\QueueManager;

class IndexingModule
{
    private EventManager $eventManager;
    private QueueManager $queueManager;

    public function __construct(EventManager $eventManager, QueueManager $queueManager)
    {
        $this->eventManager = $eventManager;
        $this->queueManager = $queueManager;
    }

    public function boot(): void
    {
        // Hook into post publication/update
        $this->eventManager->addAction('transition_post_status', [$this, 'handlePostTransition'], 10, 3);
    }

    public function handlePostTransition(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        if ($newStatus !== 'publish') {
            return;
        }

        $autoIndex = get_option('ranksavvy_auto_index', 'yes');
        if ($autoIndex !== 'yes') {
            return; // Skip automatic indexing if disabled in settings
        }

        // Check if the post is set to noindex
        $noindex = get_post_meta($post->ID, '_ranksavvy_noindex', true);
        if ($noindex === 'yes') {
            return;
        }

        $url = get_permalink($post->ID);
        if (!$url) {
            return;
        }

        // Determine action based on status transition
        $action = ($oldStatus === 'publish') ? 'URL_UPDATED' : 'URL_UPDATED'; // Google API uses URL_UPDATED for both

        // Dispatch background job to ping APIs without blocking the save request
        $this->queueManager->push(IndexingJob::class, [
            'post_id' => $post->ID,
            'url'     => $url,
            'action'  => $action
        ]);
    }
}
