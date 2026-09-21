<?php

namespace AmEveryWhere\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UpgradePromptManager
 *
 * Drives free→paid conversion with contextual upgrade prompts, a dismissible
 * banner, and a feature availability API used by other modules to gate Pro
 * features and provide upgrade URLs.
 *
 * BL-012
 */
class UpgradePromptManager {

	private const TIER_OPTION      = 'ameverywhere_user_tier';
	private const DISMISSED_META   = 'ameverywhere_upgrade_banner_dismissed';
	private const UPGRADE_BASE_URL = 'https://ameverywhere.com/upgrade';

	private const PRO_FEATURES = array(
		'writing_assistant'     => array(
			'name' => 'AI Writing Assistant',
			'tier' => 'pro',
		),
		'content_gap'           => array(
			'name' => 'Content Gap Analysis',
			'tier' => 'pro',
		),
		'rank_tracker'          => array(
			'name' => 'Keyword Rank Tracker',
			'tier' => 'pro',
		),
		'agency_workspace'      => array(
			'name' => 'Multi-Brand Workspaces',
			'tier' => 'enterprise',
		),
		'schema_aggregation'    => array(
			'name' => 'Schema Competitor Import',
			'tier' => 'pro',
		),
		'ai_visibility_tracker' => array(
			'name' => 'AI Visibility Tracker',
			'tier' => 'enterprise',
		),
		'gsc_integration'       => array(
			'name' => 'Google Search Console',
			'tier' => 'pro',
		),
		'technical_audit'       => array(
			'name' => 'Full Technical SEO Audit',
			'tier' => 'pro',
		),
		'client_reports'        => array(
			'name' => 'Client Reporting Portal',
			'tier' => 'enterprise',
		),
		'bulk_csv_redirects'    => array(
			'name' => 'Bulk CSV Redirects',
			'tier' => 'pro',
		),
	);

	public function boot(): void {
		add_action( 'admin_notices', array( $this, 'showUpgradeBannerNotice' ) );
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
		add_action( 'wp_ajax_ameverywhere_dismiss_upgrade_banner', array( $this, 'handleDismissAjax' ) );
	}

	// ── Admin banner ──────────────────────────────────────────────────────────

	public function showUpgradeBannerNotice(): void {
		$tier = get_option( self::TIER_OPTION, 'free' );
		if ( $tier !== 'free' ) {
			return;
		}

		$userId    = get_current_user_id();
		$dismissed = (bool) get_user_meta( $userId, self::DISMISSED_META, true );
		if ( $dismissed ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'ameverywhere' ) === false ) {
			return;
		}

		$nonce      = wp_create_nonce( 'ameverywhere_dismiss_banner' );
		$upgradeUrl = $this->getUpgradeUrl( 'dashboard_banner' );

		echo '<div class="notice notice-info is-dismissible" id="ameverywhere-upgrade-banner" style="border-left-color:#4f46e5;">';
		echo '<p><strong>🚀 Unlock AmEveryWhere Pro</strong> — AI Writing Assistant, Rank Tracker, GSC Integration, and more. ';
		echo '<a href="' . esc_url( $upgradeUrl ) . '" target="_blank" style="font-weight:600;">Upgrade now →</a></p>';
		echo '</div>';

		echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var banner = document.getElementById("ameverywhere-upgrade-banner");
            if (!banner) return;
            banner.addEventListener("click", function(e) {
                if (e.target.classList.contains("notice-dismiss")) {
                    fetch(ajaxurl, {
                        method: "POST",
                        headers: {"Content-Type": "application/x-www-form-urlencoded"},
                        body: "action=ameverywhere_dismiss_upgrade_banner&_ajax_nonce=' . esc_js( $nonce ) . '"
                    });
                }
            });
        });
        </script>';
	}

	public function handleDismissAjax(): void {
		check_ajax_referer( 'ameverywhere_dismiss_banner' );
		update_user_meta( get_current_user_id(), self::DISMISSED_META, true );
		wp_die();
	}

	// ── REST routes ───────────────────────────────────────────────────────────

	public function registerRoutes(): void {
		$editorCap = fn() => current_user_can( 'edit_posts' );

		register_rest_route(
			'ameverywhere/v1',
			'/upgrade/features',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'listFeatures' ),
				'permission_callback' => $editorCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/upgrade/dismiss-banner',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'dismissBanner' ),
				'permission_callback' => $editorCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/upgrade/check-feature',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkFeature' ),
				'permission_callback' => $editorCap,
			)
		);
	}

	public function listFeatures( \WP_REST_Request $request ): \WP_REST_Response {
		$currentTier = get_option( self::TIER_OPTION, 'free' );
		$features    = array();

		foreach ( self::PRO_FEATURES as $key => $info ) {
			$features[] = array(
				'key'           => $key,
				'name'          => $info['name'],
				'tier_required' => $info['tier'],
				'available'     => $this->isFeatureAvailable( $key ),
				'upgrade_url'   => $this->getUpgradeUrl( $key ),
			);
		}

		return rest_ensure_response(
			array(
				'current_tier' => $currentTier,
				'features'     => $features,
			)
		);
	}

	public function dismissBanner( \WP_REST_Request $request ): \WP_REST_Response {
		update_user_meta( get_current_user_id(), self::DISMISSED_META, true );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function checkFeature( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$key    = sanitize_text_field( $params['feature_key'] ?? '' );

		if ( ! isset( self::PRO_FEATURES[ $key ] ) ) {
			return new \WP_Error( 'unknown_feature', 'Unknown feature key.', array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'feature_key'   => $key,
				'available'     => $this->isFeatureAvailable( $key ),
				'tier_required' => self::PRO_FEATURES[ $key ]['tier'],
				'upgrade_url'   => $this->getUpgradeUrl( $key ),
			)
		);
	}

	// ── Feature gating ────────────────────────────────────────────────────────

	public function isFeatureAvailable( string $featureKey ): bool {
		$currentTier = get_option( self::TIER_OPTION, 'free' );

		if ( $currentTier === 'enterprise' ) {
			return true;
		}

		if ( $currentTier === 'pro' ) {
			$requiredTier = self::PRO_FEATURES[ $featureKey ]['tier'] ?? 'pro';
			return $requiredTier !== 'enterprise';
		}

		return false; // free tier: no Pro features
	}

	public function getUpgradeUrl( string $featureKey ): string {
		return add_query_arg(
			array(
				'utm_source'   => 'plugin',
				'utm_medium'   => 'feature_lock',
				'utm_campaign' => $featureKey,
			),
			self::UPGRADE_BASE_URL
		);
	}
}
