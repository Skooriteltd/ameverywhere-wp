<?php

namespace RankSavvy\Modules\Indexing;

class BingIndexNowApi
{
    public function ping(string $url): bool
    {
        // IndexNow requires an API key which is often just a random string matching a .txt file on the root.
        $apiKey = get_option('ranksavvy_indexnow_key');
        $host = parse_url(home_url(), PHP_URL_HOST);
        
        if (empty($apiKey) || empty($host)) {
            return false;
        }

        $endpoint = 'https://api.indexnow.org/indexnow';
        
        $body = wp_json_encode([
            'host'        => $host,
            'key'         => $apiKey,
            'keyLocation' => home_url("/{$apiKey}.txt"),
            'urlList'     => [$url]
        ]);

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'body'    => $body,
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code === 200 || $code === 202;
    }
}
