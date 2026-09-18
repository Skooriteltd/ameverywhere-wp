<?php

namespace AmEveryWhere\Modules\Indexing;

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

        if (empty($url)) {
            return;
        }

        $quotaManager = new QuotaManager();
        
        if ($quotaManager->canPingGoogle()) {
            $googleApi = new GoogleIndexingApi();
            $googleSuccess = $googleApi->ping($url, $action);
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
