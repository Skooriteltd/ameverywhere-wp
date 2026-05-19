<?php

namespace RankSavvy\Modules\TechnicalSeo;

/**
 * Handles, matches, and performs 301/302/307 redirects.
 * Exposes a production-grade REST API for Redirect CRUD.
 */
class RedirectManager
{
    private const OPTION_KEY = 'ranksavvy_redirects';

    /**
     * Intercept front-end requests and perform redirects if matching rule is found.
     */
    public function handleRedirects(): void
    {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $redirects = $this->getRedirectRules();
        if (empty($redirects)) {
            return;
        }

        $currentPath = $this->getCurrentPath();

        foreach ($redirects as $rule) {
            $source = trim($rule['source']);
            $target = trim($rule['target']);
            $code = intval($rule['code'] ?? 301);
            $isRegex = !empty($rule['is_regex']);

            // Normalize source path for exact comparison
            $cleanSource = $this->normalizePath($source);
            $cleanCurrent = $this->normalizePath($currentPath);

            // Exact Match
            if (!$isRegex && $cleanCurrent === $cleanSource) {
                $this->performRedirect($target, $code);
            }

            // Regex Match
            if ($isRegex) {
                // Ensure regex pattern is bounded and safe
                $pattern = '/' . str_replace('/', '\/', ltrim($source, '/')) . '/i';
                if (@preg_match($pattern, $currentPath)) {
                    $newTarget = @preg_replace($pattern, $target, $currentPath);
                    if ($newTarget) {
                        $this->performRedirect($newTarget, $code);
                    }
                }
            }
        }
    }

    /**
     * Get redirects rules list.
     */
    public function getRedirectRules(): array
    {
        return get_option(self::OPTION_KEY, []);
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

        $redirects = $this->getRedirectRules();

        // Check for duplicates
        foreach ($redirects as $rule) {
            if ($rule['source'] === $source) {
                return new \WP_Error('duplicate', 'A redirect with this source already exists.', ['status' => 400]);
            }
        }

        $id = uniqid('redir_', false);
        $redirects[] = [
            'id'       => $id,
            'source'   => $source,
            'target'   => $target,
            'code'     => $code,
            'is_regex' => $isRegex,
        ];

        update_option(self::OPTION_KEY, $redirects);

        return rest_ensure_response(['success' => true, 'redirects' => $redirects]);
    }

    public function deleteRedirectEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $id = sanitize_text_field($params['id'] ?? '');

        if (empty($id)) {
            return new \WP_Error('missing_id', 'Redirect ID is required.', ['status' => 400]);
        }

        $redirects = $this->getRedirectRules();
        $filtered = array_filter($redirects, function ($rule) use ($id) {
            return ($rule['id'] ?? '') !== $id;
        });

        // Re-index array
        $filtered = array_values($filtered);
        update_option(self::OPTION_KEY, $filtered);

        return rest_ensure_response(['success' => true, 'redirects' => $filtered]);
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
