<?php

namespace RankSavvy\Modules\TechnicalSeo;

/**
 * Handles, matches, and performs 301/302/307 redirects.
 * Exposes a production-grade REST API for Redirect CRUD.
 *
 * Performance Architecture:
 *   - Exact matches: Direct SQL lookup by source URL (indexed column).
 *   - Regex rules: Loaded separately and cached via wp_cache (Object Cache API).
 *   - No full-table loads on the frontend — zero-bloat at scale.
 */
class RedirectManager
{
    private const OPTION_KEY = 'ranksavvy_redirects';
    private const REGEX_CACHE_KEY = 'ranksavvy_regex_redirects';
    private const REGEX_CACHE_GROUP = 'ranksavvy';

    /**
     * Intercept front-end requests and perform redirects if matching rule is found.
     */
    public function handleRedirects(): void
    {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $currentPath = $this->getCurrentPath();
        $cleanCurrent = $this->normalizePath($currentPath);

        // ── 1. Fast Exact Match via direct SQL ──
        $exactMatch = $this->findExactMatch($cleanCurrent);
        if ($exactMatch) {
            $this->performRedirect($exactMatch->target, (int) $exactMatch->code);
        }

        // ── 2. Regex Match via cached rule set ──
        $regexRules = $this->getRegexRules();
        foreach ($regexRules as $rule) {
            $pattern = '/' . str_replace('/', '\\/', ltrim($rule->source, '/')) . '/i';
            if (@preg_match($pattern, $currentPath)) {
                $newTarget = @preg_replace($pattern, $rule->target, $currentPath);
                if ($newTarget) {
                    $this->performRedirect($newTarget, (int) $rule->code);
                }
            }
        }
    }

    /**
     * Find an exact-match redirect by normalized source path.
     * Uses a direct indexed query — O(1) lookup instead of loading all rows.
     */
    private function findExactMatch(string $normalizedPath): ?object
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ranksavvy_redirects';

        // Look up both the normalized path and common path variations
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT target, code FROM $table WHERE source = %s AND is_regex = 0 LIMIT 1",
                $normalizedPath
            )
        );

        // Also try with trailing slash stripped/added for flexibility
        if (!$result) {
            $alt = (substr($normalizedPath, -1) === '/')
                ? rtrim($normalizedPath, '/')
                : $normalizedPath . '/';

            $result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT target, code FROM $table WHERE source = %s AND is_regex = 0 LIMIT 1",
                    $alt
                )
            );
        }

        return $result ?: null;
    }

    /**
     * Get regex redirect rules from Object Cache (Memcached/Redis aware).
     * Falls back to a single focused query if the cache is cold.
     */
    private function getRegexRules(): array
    {
        $cached = wp_cache_get(self::REGEX_CACHE_KEY, self::REGEX_CACHE_GROUP);
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ranksavvy_redirects';

        $rules = $wpdb->get_results(
            "SELECT source, target, code FROM $table WHERE is_regex = 1"
        );

        if (!is_array($rules)) {
            $rules = [];
        }

        // Cache for 1 hour — flushed on redirect CRUD operations
        wp_cache_set(self::REGEX_CACHE_KEY, $rules, self::REGEX_CACHE_GROUP, HOUR_IN_SECONDS);

        return $rules;
    }

    /**
     * Invalidate the regex rules cache (called after any redirect CRUD).
     */
    private function flushRegexCache(): void
    {
        wp_cache_delete(self::REGEX_CACHE_KEY, self::REGEX_CACHE_GROUP);
    }

    /**
     * Get all redirect rules (for the admin REST API only — not used on frontend).
     */
    public function getRedirectRules(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ranksavvy_redirects';

        $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);

        // Fallback and migration logic for legacy wp_options storage
        $legacyRedirects = get_option(self::OPTION_KEY);
        if (empty($rows) && !empty($legacyRedirects) && is_array($legacyRedirects)) {
            foreach ($legacyRedirects as $rule) {
                $wpdb->insert($table, [
                    'id'       => $rule['id'] ?? uniqid('redir_', false),
                    'source'   => $rule['source'] ?? '',
                    'target'   => $rule['target'] ?? '',
                    'code'     => intval($rule['code'] ?? 301),
                    'is_regex' => !empty($rule['is_regex']) ? 1 : 0
                ]);
            }
            $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            delete_option(self::OPTION_KEY);
        }

        if (empty($rows)) {
            return [];
        }

        // Standardize types
        foreach ($rows as &$row) {
            $row['is_regex'] = !empty($row['is_regex']);
            $row['code'] = intval($row['code']);
        }

        return $rows;
    }

    /**
     * Expose REST routes for Redirect CRUD.
     */
    public function registerRoutes(): void
    {
        register_rest_route('ranksavvy/v1', '/redirects', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getRedirectsEndpoint'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'addRedirectEndpoint'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route('ranksavvy/v1', '/redirects/delete', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'deleteRedirectEndpoint'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getRedirectsEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response($this->getRedirectRules());
    }

    public function addRedirectEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        $source = sanitize_text_field($params['source'] ?? '');
        $target = sanitize_text_field($params['target'] ?? '');
        $code = intval($params['code'] ?? 301);
        $isRegex = !empty($params['is_regex']);

        if (empty($source) || empty($target)) {
            return new \WP_Error('invalid_fields', 'Source and Target URLs are required.', ['status' => 400]);
        }

        if (!in_array($code, [301, 302, 307, 410, 451], true)) {
            $code = 301;
        }

        // Check for duplicate source via direct query instead of loading all rules
        global $wpdb;
        $table = $wpdb->prefix . 'ranksavvy_redirects';

        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE source = %s", $source)
        );
        if ($existing > 0) {
            return new \WP_Error('duplicate', 'A redirect with this source already exists.', ['status' => 400]);
        }

        $id = uniqid('redir_', false);

        $inserted = $wpdb->insert($table, [
            'id'       => $id,
            'source'   => $source,
            'target'   => $target,
            'code'     => $code,
            'is_regex' => $isRegex ? 1 : 0,
        ]);

        if ($inserted === false) {
            return new \WP_Error('db_error', 'Failed to save redirect inside the database.', ['status' => 500]);
        }

        // Flush regex cache so new rules take effect immediately
        $this->flushRegexCache();

        $updatedRedirects = $this->getRedirectRules();

        return rest_ensure_response(['success' => true, 'redirects' => $updatedRedirects]);
    }

    public function deleteRedirectEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $id = sanitize_text_field($params['id'] ?? '');

        if (empty($id)) {
            return new \WP_Error('missing_id', 'Redirect ID is required.', ['status' => 400]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ranksavvy_redirects';

        $deleted = $wpdb->delete($table, ['id' => $id]);

        if ($deleted === false) {
            return new \WP_Error('db_error', 'Failed to delete redirect from the database.', ['status' => 500]);
        }

        // Flush regex cache
        $this->flushRegexCache();

        $updatedRedirects = $this->getRedirectRules();

        return rest_ensure_response(['success' => true, 'redirects' => $updatedRedirects]);
    }

    /**
     * Get the relative request path.
     */
    private function getCurrentPath(): string
    {
        $path = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $pathParts = explode('?', $path);
        return $pathParts[0];
    }

    /**
     * Clean and normalize a path.
     */
    private function normalizePath(string $url): string
    {
        // If absolute URL, extract path
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            $parsed = wp_parse_url($url);
            $url = $parsed['path'] ?? '/';
        }

        return '/' . trim($url, '/');
    }

    /**
     * Redirect and exit cleanly.
     */
    private function performRedirect(string $target, int $code): void
    {
        // Support relative targets
        if (strpos($target, 'http://') !== 0 && strpos($target, 'https://') !== 0) {
            $target = home_url($target);
        }

        wp_redirect($target, $code);
        exit;
    }
}
