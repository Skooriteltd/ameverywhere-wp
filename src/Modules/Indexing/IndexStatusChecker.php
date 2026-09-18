<?php

namespace AmEveryWhere\Modules\Indexing;

/**
 * IndexStatusChecker
 *
 * Checks indexation status of posts via the Google Search Console
 * URL Inspection API (requires OAuth token). Caches results for 1 hour.
 * Provides helpful troubleshooting tips when pages are not indexed.
 *
 * BL-017
 */
class IndexStatusChecker
{
    private const CACHE_TTL        = HOUR_IN_SECONDS;
    private const GSC_TOKEN_OPTION = 'ameverywhere_gsc_access_token';
    private const MAX_BULK         = 10;

    private const INSPECT_ENDPOINT = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    // ── REST routes ───────────────────────────────────────────────────────────

    public function registerRoutes(): void
    {
        $editorCap = fn() => current_user_can('edit_posts');

        register_rest_route('ameverywhere/v1', '/indexing/check-status', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'checkStatus'],
            'permission_callback' => $editorCap,
        ]);

        register_rest_route('ameverywhere/v1', '/indexing/check-status-bulk', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'checkStatusBulk'],
            'permission_callback' => $editorCap,
        ]);
    }

    // ── REST callbacks ────────────────────────────────────────────────────────

    public function checkStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $postId = absint($params['post_id'] ?? 0);
        $url    = sanitize_url($params['url'] ?? '');

        if ($postId > 0) {
            $url = get_permalink($postId);
        }

        if (empty($url)) {
            return new \WP_Error('missing_url', 'Provide either post_id or url.', ['status' => 400]);
        }

        return rest_ensure_response($this->inspect($url));
    }

    public function checkStatusBulk(\WP_REST_Request $request): \WP_REST_Response
    {
        $params   = $request->get_json_params();
        $postIds  = array_slice(array_map('absint', $params['post_ids'] ?? []), 0, self::MAX_BULK);

        if (empty($postIds)) {
            return new \WP_Error('missing_ids', 'Provide post_ids array (max ' . self::MAX_BULK . ').', ['status' => 400]);
        }

        $results = [];
        foreach ($postIds as $id) {
            $url = get_permalink($id);
            if ($url) {
                $results[$id] = $this->inspect($url);
            }
        }

        return rest_ensure_response(['results' => $results]);
    }

    // ── Core inspection ───────────────────────────────────────────────────────

    private function inspect(string $url): array
    {
        $cacheKey = 'ameverywhere_index_status_' . md5($url);
        $cached   = get_transient($cacheKey);
        if ($cached !== false) {
            return array_merge($cached, ['cached' => true]);
        }

        $token = get_option(self::GSC_TOKEN_OPTION, '');
        if (empty($token)) {
            return [
                'url'                => $url,
                'status'             => 'unconfigured',
                'message'            => 'Google Search Console is not connected. Go to AmEveryWhere → Settings → Integrations to connect.',
                'cached'             => false,
                'troubleshooting_tips' => [],
            ];
        }

        $siteUrl = site_url('/');

        $response = wp_remote_post(self::INSPECT_ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'inspectionUrl' => $url,
                'siteUrl'       => $siteUrl,
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return [
                'url'    => $url,
                'status' => 'error',
                'message' => $response->get_error_message(),
                'cached' => false,
                'troubleshooting_tips' => [],
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return [
                'url'    => $url,
                'status' => 'api_error',
                'message' => 'GSC API returned HTTP ' . $code . '. Your access token may have expired.',
                'cached' => false,
                'troubleshooting_tips' => ['Re-authorise your Google Search Console connection in AmEveryWhere → Settings → Integrations.'],
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $result = $this->parseInspectionResult($url, $body);

        set_transient($cacheKey, $result, self::CACHE_TTL);

        return array_merge($result, ['cached' => false]);
    }

    private function parseInspectionResult(string $url, array $body): array
    {
        $ir      = $body['inspectionResult'] ?? [];
        $index   = $ir['indexStatusResult'] ?? [];
        $verdict = $index['verdict'] ?? 'UNKNOWN';
        $coverage = $index['coverageState'] ?? '';

        $tips = $this->buildTroubleshootingTips($verdict, $coverage, $index);

        return [
            'url'               => $url,
            'verdict'           => $verdict,
            'coverage_state'    => $coverage,
            'robots_txt_state'  => $index['robotsTxtState']  ?? '',
            'indexing_state'    => $index['indexingState']   ?? '',
            'page_fetch_state'  => $index['pageFetchState']  ?? '',
            'last_crawl_time'   => $index['lastCrawlTime']   ?? null,
            'troubleshooting_tips' => $tips,
        ];
    }

    private function buildTroubleshootingTips(string $verdict, string $coverage, array $index): array
    {
        $tips = [];

        if ($verdict === 'PASS') {
            return ['Page is indexed and appears in Google Search. 🎉'];
        }

        if (($index['robotsTxtState'] ?? '') === 'DISALLOWED') {
            $tips[] = 'This URL is blocked by your robots.txt. Go to AmEveryWhere → Technical SEO → Robots.txt to review.';
        }

        if (($index['indexingState'] ?? '') === 'INDEXING_NOT_ALLOWED') {
            $tips[] = 'A noindex directive is preventing indexing. Check the Meta Robots field in the AmEveryWhere SEO panel for this post.';
        }

        if (stripos($coverage, 'Crawled - currently not indexed') !== false) {
            $tips[] = 'Google has crawled this page but chosen not to index it. Consider improving content quality, canonical tags, and internal links.';
        }

        if (stripos($coverage, 'Discovered - currently not indexed') !== false) {
            $tips[] = 'Google discovered this URL but hasn\'t crawled it yet. Improve internal links to this page and resubmit via sitemap.';
        }

        if (stripos($coverage, 'Duplicate') !== false) {
            $tips[] = 'Google considers this a duplicate of another URL. Check that the canonical tag points to the correct primary URL.';
        }

        if (empty($tips)) {
            $tips[] = 'Review the URL in Google Search Console for full details: https://search.google.com/search-console';
        }

        return $tips;
    }
}
