<?php

namespace RankSavvy\Modules\Trends;

class GoogleTrendsClient
{
    private const BASE_URL = 'https://trends.google.com/trends/api';

    public function fetchTrendData(string $keyword, string $geo = ''): array
    {
        $cacheKey = 'ranksavvy_trend_' . md5($keyword . $geo);
        $cached = get_transient($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Step 1: Get Explore Token
        $req = [
            'comparisonItem' => [
                [
                    'keyword' => $keyword,
                    'geo' => $geo,
                    'time' => 'today 12-m' // Last 12 months
                ]
            ],
            'category' => 0,
            'property' => ''
        ];

        $exploreUrl = self::BASE_URL . '/explore?hl=en-US&tz=0&req=' . urlencode(wp_json_encode($req));
        
        $response = wp_remote_get($exploreUrl, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json, text/plain, */*',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Cookie' => 'NID=511=...' // Basic cookie stub to prevent some immediate 429s
            ]
        ]);

        if (is_wp_error($response)) {
            return ['error' => 'Failed to connect to Google Trends.'];
        }

        $body = wp_remote_retrieve_body($response);
        // Google Trends prefixes their JSON with ")]}',\n"
        $body = preg_replace('/^\)\]\}\',\n/', '', $body);
        $exploreData = json_decode($body, true);

        if (!$exploreData || !isset($exploreData['widgets'][0]['request']) || !isset($exploreData['widgets'][0]['token'])) {
            return ['error' => 'Failed to parse Google Trends token. Ensure your server IP is not blocked.'];
        }

        $widgetReq = $exploreData['widgets'][0]['request'];
        $token = $exploreData['widgets'][0]['token'];

        // Step 2: Get Time Series Data
        $widgetUrl = self::BASE_URL . '/widgetdata/multiline?hl=en-US&tz=0&req=' . urlencode(wp_json_encode($widgetReq)) . '&token=' . urlencode($token);
        
        $response2 = wp_remote_get($widgetUrl, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json, text/plain, */*',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ]
        ]);

        if (is_wp_error($response2)) {
            return ['error' => 'Failed to fetch trend data.'];
        }

        $body2 = wp_remote_retrieve_body($response2);
        $body2 = preg_replace('/^\)\]\}\',\n/', '', $body2);
        $trendData = json_decode($body2, true);

        if (!$trendData || !isset($trendData['default']['timelineData'])) {
            return ['error' => 'Failed to parse timeline data.'];
        }

        $formattedData = [];
        foreach ($trendData['default']['timelineData'] as $point) {
            $formattedData[] = [
                'time' => $point['formattedAxisTime'],
                'value' => (int) $point['value'][0]
            ];
        }

        // Cache for 12 hours
        set_transient($cacheKey, $formattedData, 12 * HOUR_IN_SECONDS);

        return $formattedData;
    }
}
