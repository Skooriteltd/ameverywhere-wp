<?php

namespace AmEveryWhere\Modules\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GoogleSearchConsoleIntegration
 *
 * BL-030: OAuth2 Google Search Console connection.
 * Fetches impressions, clicks, CTR, position per URL and
 * "Top Declining Keywords" trend data.
 */
class GoogleSearchConsoleIntegration
{
    private const TOKEN_OPTION  = 'ameverywhere_gsc_token';
    private const CONFIG_OPTION = 'ameverywhere_gsc_config';
    private const CACHE_TTL     = 6 * HOUR_IN_SECONDS;

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $adminCap  = fn() => current_user_can('manage_options');
        $reportCap = fn() => current_user_can('view_seo_reports') || current_user_can('manage_options');

        register_rest_route('ameverywhere/v1', '/gsc/auth-url', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getAuthUrl'],
            'permission_callback' => $adminCap,
        ]);

        register_rest_route('ameverywhere/v1', '/gsc/callback', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handleCallback'],
            'permission_callback' => $adminCap,
        ]);

        register_rest_route('ameverywhere/v1', '/gsc/disconnect', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'disconnect'],
            'permission_callback' => $adminCap,
        ]);

        register_rest_route('ameverywhere/v1', '/gsc/status', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getStatus'],
            'permission_callback' => $adminCap,
        ]);

        register_rest_route('ameverywhere/v1', '/gsc/performance', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getPerformance'],
            'permission_callback' => $reportCap,
            'args'                => [
                'days'    => ['default' => 28, 'sanitize_callback' => 'absint'],
                'page'    => ['default' => '', 'sanitize_callback' => 'sanitize_url'],
                'keyword' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route('ameverywhere/v1', '/gsc/declining-keywords', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getDecliningKeywords'],
            'permission_callback' => $reportCap,
        ]);
    }

    public function getAuthUrl(\WP_REST_Request $request): \WP_REST_Response
    {
        $config = $this->getConfig();

        if (empty($config['client_id'])) {
            return new \WP_Error('no_config', 'Set your Google OAuth client_id and client_secret in AmEveryWhere → Settings → Integrations.', ['status' => 400]);
        }

        $state       = wp_create_nonce('ameverywhere_gsc_oauth');
        $redirectUri = rest_url('ameverywhere/v1/gsc/callback');

        $params = http_build_query([
            'client_id'             => $config['client_id'],
            'redirect_uri'          => $redirectUri,
            'response_type'         => 'code',
            'scope'                 => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type'           => 'offline',
            'prompt'                => 'consent',
            'state'                 => $state,
        ]);

        return rest_ensure_response([
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth?' . $params,
        ]);
    }

    public function handleCallback(\WP_REST_Request $request): \WP_REST_Response
    {
        $code  = sanitize_text_field($request->get_param('code') ?? '');
        $state = sanitize_text_field($request->get_param('state') ?? '');

        if (!wp_verify_nonce($state, 'ameverywhere_gsc_oauth')) {
            return new \WP_Error('invalid_state', 'OAuth state mismatch. Possible CSRF.', ['status' => 403]);
        }

        if (empty($code)) {
            return new \WP_Error('no_code', 'No authorization code received.', ['status' => 400]);
        }

        $config = $this->getConfig();
        $token  = $this->exchangeCode($code, $config);

        if (is_wp_error($token)) {
            return $token;
        }

        update_option(self::TOKEN_OPTION, $token);

        return rest_ensure_response(['success' => true, 'message' => 'Google Search Console connected successfully.']);
    }

    public function disconnect(\WP_REST_Request $request): \WP_REST_Response
    {
        delete_option(self::TOKEN_OPTION);
        return rest_ensure_response(['success' => true]);
    }

    public function getStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $token = get_option(self::TOKEN_OPTION);
        return rest_ensure_response([
            'connected' => !empty($token['access_token']),
            'expires_at' => $token['expires_at'] ?? null,
            'site_url'   => home_url(),
        ]);
    }

    public function getPerformance(\WP_REST_Request $request): \WP_REST_Response
    {
        $days    = min(90, max(1, (int) $request->get_param('days')));
        $page    = $request->get_param('page');
        $keyword = $request->get_param('keyword');

        $cacheKey = 'gsc_perf_' . md5($days . $page . $keyword);
        $cached   = get_transient($cacheKey);
        if ($cached !== false) {
            return rest_ensure_response(array_merge($cached, ['cached' => true]));
        }

        $token = $this->getValidToken();
        if (is_wp_error($token)) {
            return $token;
        }

        $endDate   = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $body = [
            'startDate'  => $startDate,
            'endDate'    => $endDate,
            'dimensions' => ['query', 'page'],
            'rowLimit'   => 100,
        ];

        if ($page) {
            $body['dimensionFilterGroups'] = [[
                'filters' => [['dimension' => 'page', 'operator' => 'equals', 'expression' => $page]],
            ]];
        }

        if ($keyword) {
            $body['dimensionFilterGroups'] = [[
                'filters' => [['dimension' => 'query', 'operator' => 'contains', 'expression' => $keyword]],
            ]];
        }

        $siteUrl  = urlencode(home_url('/'));
        $response = wp_remote_post(
            "https://www.googleapis.com/webmasters/v3/sites/{$siteUrl}/searchAnalytics/query",
            [
                'timeout' => 20,
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'body'    => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['error'])) {
            return new \WP_Error('gsc_error', $data['error']['message'] ?? 'GSC error.', ['status' => 502]);
        }

        $result = ['rows' => $data['rows'] ?? [], 'period_days' => $days];
        set_transient($cacheKey, $result, self::CACHE_TTL);

        return rest_ensure_response(array_merge($result, ['cached' => false]));
    }

    public function getDecliningKeywords(\WP_REST_Request $request): \WP_REST_Response
    {
        $token = $this->getValidToken();
        if (is_wp_error($token)) {
            return $token;
        }

        $siteUrl   = urlencode(home_url('/'));
        $today     = date('Y-m-d');
        $periods   = [
            'recent' => [date('Y-m-d', strtotime('-28 days')), $today],
            'prior'  => [date('Y-m-d', strtotime('-56 days')), date('Y-m-d', strtotime('-29 days'))],
        ];

        $data = [];
        foreach ($periods as $label => [$start, $end]) {
            $response = wp_remote_post(
                "https://www.googleapis.com/webmasters/v3/sites/{$siteUrl}/searchAnalytics/query",
                [
                    'timeout' => 20,
                    'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                    'body'    => wp_json_encode(['startDate' => $start, 'endDate' => $end, 'dimensions' => ['query'], 'rowLimit' => 200]),
                ]
            );
            if (!is_wp_error($response)) {
                $body       = json_decode(wp_remote_retrieve_body($response), true);
                $data[$label] = array_column($body['rows'] ?? [], null, 'keys');
            }
        }

        // Compare: find keywords where position worsened or clicks dropped
        $declining = [];
        foreach ($data['recent'] ?? [] as $keyArr => $row) {
            $kw     = is_array($row['keys']) ? $row['keys'][0] : $keyArr;
            $prior  = $data['prior'][$keyArr] ?? null;
            if ($prior && ($row['clicks'] < ($prior['clicks'] * 0.8) || $row['position'] > ($prior['position'] + 3))) {
                $declining[] = [
                    'keyword'          => $kw,
                    'recent_clicks'    => $row['clicks'],
                    'prior_clicks'     => $prior['clicks'],
                    'recent_position'  => round($row['position'], 1),
                    'prior_position'   => round($prior['position'], 1),
                    'click_change_pct' => round((($row['clicks'] - $prior['clicks']) / max(1, $prior['clicks'])) * 100, 1),
                ];
            }
        }

        usort($declining, fn($a, $b) => $a['click_change_pct'] - $b['click_change_pct']);

        return rest_ensure_response(['declining_keywords' => array_slice($declining, 0, 20)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getValidToken(): string|\WP_Error
    {
        $token = get_option(self::TOKEN_OPTION);
        if (empty($token['access_token'])) {
            return new \WP_Error('not_connected', 'Google Search Console not connected.', ['status' => 401]);
        }

        // Refresh if expired
        if (!empty($token['expires_at']) && time() > (int) $token['expires_at'] - 60) {
            if (!empty($token['refresh_token'])) {
                $refreshed = $this->refreshToken($token['refresh_token']);
                if (!is_wp_error($refreshed)) {
                    $token = $refreshed;
                    update_option(self::TOKEN_OPTION, $token);
                }
            }
        }

        return $token['access_token'];
    }

    private function exchangeCode(string $code, array $config): array|\WP_Error
    {
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'body'    => [
                'code'          => $code,
                'client_id'     => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri'  => rest_url('ameverywhere/v1/gsc/callback'),
                'grant_type'    => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['error'])) {
            return new \WP_Error('oauth_error', $data['error_description'] ?? 'OAuth error.', ['status' => 400]);
        }

        return [
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_at'    => time() + (int) ($data['expires_in'] ?? 3600),
        ];
    }

    private function refreshToken(string $refreshToken): array|\WP_Error
    {
        $config   = $this->getConfig();
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'body'    => [
                'refresh_token' => $refreshToken,
                'client_id'     => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'grant_type'    => 'refresh_token',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['error'])) {
            return new \WP_Error('refresh_error', $data['error_description'] ?? 'Token refresh error.', ['status' => 401]);
        }

        $existing = get_option(self::TOKEN_OPTION, []);
        return array_merge($existing, [
            'access_token' => $data['access_token'],
            'expires_at'   => time() + (int) ($data['expires_in'] ?? 3600),
        ]);
    }

    private function getConfig(): array
    {
        return (array) get_option(self::CONFIG_OPTION, ['client_id' => '', 'client_secret' => '']);
    }
}
