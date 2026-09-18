<?php

namespace AmEveryWhere\Modules\Compliance;

/**
 * CookieBannerModule: Lightweight CCPA/GDPR cookie consent banner.
 *
 * Injects a consent bar into wp_footer with zero JS dependencies.
 * Supports two jurisdiction modes:
 *   - ccpa: opt-out model (consent assumed, decline available)
 *   - gdpr: opt-in model (no scripts until accepted)
 * Stores consent in a first-party cookie (ameverywhere_consent).
 */
class CookieBannerModule
{
    private const OPTION_KEY       = 'ameverywhere_cookie_banner';
    private const CONSENT_COOKIE   = 'ameverywhere_consent';

    public function boot(): void
    {
        add_action('wp_footer',          [$this, 'renderBanner'], 100);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('rest_api_init',      [$this, 'registerRoutes']);
    }

    public function enqueueAssets(): void
    {
        $config = $this->getConfig();
        if (!$config['enabled']) {
            return;
        }

        $pluginUrl = plugin_dir_url(dirname(__DIR__, 2) . '/ameverywhere.php');

        wp_enqueue_style(
            'ameverywhere-cookie-banner',
            $pluginUrl . 'build/cookie-banner.css',
            [],
            AMEVERYWHERE_VERSION ?? '1.0.0'
        );

        wp_enqueue_script(
            'ameverywhere-cookie-banner',
            $pluginUrl . 'build/cookie-banner.js',
            [],
            AMEVERYWHERE_VERSION ?? '1.0.0',
            true
        );

        wp_localize_script('ameverywhere-cookie-banner', 'amEveryWhereCookieConfig', [
            'mode'       => $config['mode'],
            'cookieName' => self::CONSENT_COOKIE,
            'cookieDays' => 365,
        ]);
    }

    public function renderBanner(): void
    {
        $config = $this->getConfig();
        if (!$config['enabled']) {
            return;
        }
        ?>
        <div id="ameverywhere-cookie-banner" class="aew-cookie-banner rs-cookie-banner" role="dialog" aria-live="polite" aria-label="Cookie consent" style="display:none;">
            <div class="rs-cookie-banner__inner">
                <p class="rs-cookie-banner__text"><?php echo wp_kses_post($config['message']); ?>
                    <?php if (!empty($config['policy_url'])): ?>
                        <a href="<?php echo esc_url($config['policy_url']); ?>" target="_blank" rel="noopener">Privacy Policy</a>.
                    <?php endif; ?>
                </p>
                <div class="rs-cookie-banner__actions">
                    <button id="rs-cookie-accept" class="rs-cookie-btn rs-cookie-btn--accept"><?php echo esc_html($config['accept_label']); ?></button>
                    <?php if ($config['mode'] === 'gdpr'): ?>
                        <button id="rs-cookie-decline" class="rs-cookie-btn rs-cookie-btn--decline"><?php echo esc_html($config['decline_label']); ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // ── REST API ─────────────────────────────────────────────────────────────

    public function registerRoutes(): void
    {
        register_rest_route('ameverywhere/v1', '/settings/cookie-banner', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSettings'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'saveSettings'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
        ]);
    }

    public function getSettings(): \WP_REST_Response
    {
        return rest_ensure_response($this->getConfig());
    }

    public function saveSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $config = [
            'enabled'       => (bool)   ($params['enabled']       ?? false),
            'mode'          => in_array($params['mode'] ?? '', ['ccpa', 'gdpr'], true) ? $params['mode'] : 'ccpa',
            'message'       => wp_kses_post($params['message']       ?? $this->defaultMessage()),
            'accept_label'  => sanitize_text_field($params['accept_label']  ?? 'Accept'),
            'decline_label' => sanitize_text_field($params['decline_label'] ?? 'Decline'),
            'policy_url'    => esc_url_raw($params['policy_url']    ?? ''),
        ];
        update_option(self::OPTION_KEY, $config);
        return rest_ensure_response(['success' => true, 'config' => $config]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getConfig(): array
    {
        $defaults = [
            'enabled'       => false,
            'mode'          => 'ccpa',
            'message'       => $this->defaultMessage(),
            'accept_label'  => 'Accept',
            'decline_label' => 'Decline',
            'policy_url'    => '',
        ];
        $saved = get_option(self::OPTION_KEY, []);
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    private function defaultMessage(): string
    {
        return 'We use cookies to improve your experience on our site. By continuing to use this site, you agree to our use of cookies.';
    }
}
