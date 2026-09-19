<?php

namespace AmEveryWhere\Modules\Indexing;

if (!defined('ABSPATH')) {
    exit;
}

class IndexingJob
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        $url = $this->data['url'] ?? '';
        $action = $this->data['action'] ?? 'URL_UPDATED';
        $postId = (int) ($this->data['post_id'] ?? 0);

        if (empty($url)) {
            return;
        }

        $quotaManager = new QuotaManager();
        
        if (
            !empty($this->data['submit_google']) &&
            get_option('ameverywhere_enable_google_indexing_api', 'no') === 'yes' &&
            $quotaManager->canPingGoogle()
        ) {
            $googleApi = new GoogleIndexingApi();
            $googleSuccess = $googleApi->ping($url, $action, $postId);
            if ($googleSuccess) {
                $quotaManager->incrementGoogle();
            }
        }

        if ($quotaManager->canPingBing()) {
            $bingApi = new BingIndexNowApi();
            $bingSuccess = $bingApi->ping($url);
            if ($bingSuccess) {
                $quotaManager->incrementBing();
            }
        }

        // Here we would log the $googleSuccess and $bingSuccess statuses to our custom indexing_logs table
    }
}
