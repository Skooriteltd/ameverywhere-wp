<?php

namespace AmEveryWhere\Modules\Onboarding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal setup wizard with zero-configuration philosophy.
 *
 * Design Principles:
 * - Everything works out of the box with sensible defaults.
 * - The wizard asks only 1-2 questions, not 10.
 * - Auto-detects site type, social profiles, and existing SEO plugins.
 * - Completes in under 30 seconds.
 */
class SetupWizard {

	private const COMPLETED_OPTION = 'ameverywhere_setup_complete';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Show welcome notice if setup hasn't been completed
		if ( ! get_option( self::COMPLETED_OPTION ) ) {
			add_action( 'admin_notices', array( $this, 'showWelcomeNotice' ) );
		}

		// Register REST routes for the wizard
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Show a dismissible welcome notice that links to the setup wizard.
	 */
	public function showWelcomeNotice(): void {
		$screen = get_current_screen();

		// Don't show on the AmEveryWhere page itself
		if ( $screen && $screen->id === 'toplevel_page_ameverywhere' ) {
			return;
		}

		$setupUrl = admin_url( 'admin.php?page=ameverywhere#setup' );

		echo '<div class="notice notice-info is-dismissible" style="border-left-color: #3b82f6; padding: 16px 20px;">';
		echo '<div style="display:flex; align-items:center; gap:12px;">';
		echo '<span style="font-size:24px;">🚀</span>';
		echo '<div>';
		echo '<p style="margin:0; font-size:15px; font-weight:600; color:#0f172a;">Welcome to AmEveryWhere!</p>';
		echo '<p style="margin:4px 0 0; color:#475569;">Your SEO is already working with smart defaults. ';
		echo '<a href="' . esc_url( $setupUrl ) . '" style="color:#3b82f6; font-weight:500;">Complete the 30-second setup</a>';
		echo ' to customize site type and import existing SEO data.</p>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Register REST routes for the wizard.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			'ameverywhere/v1',
			'/setup/auto-detect',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'autoDetect' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/setup/complete',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'completeSetup' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			)
		);
	}

	/**
	 * Auto-detect site configuration to pre-fill the wizard.
	 * This eliminates most manual configuration entirely.
	 */
	public function autoDetect( \WP_REST_Request $request ): \WP_REST_Response {
		// Detect site type from content
		$siteType = $this->detectSiteType();

		// Detect existing social profiles from other plugins or theme settings
		$socialProfiles = $this->detectSocialProfiles();

		// Detect if other SEO plugins have data to import
		$migrationManager = new \AmEveryWhere\Modules\Migration\MigrationManager();
		$detectedPlugins  = $migrationManager->detectPlugins();

		// Check what's already configured
		$hasGoogleKey = ! empty( get_option( 'ameverywhere_google_indexing_key', '' ) );
		$hasBingKey   = ! empty( get_option( 'ameverywhere_indexnow_key', '' ) );

		return rest_ensure_response(
			array(
				'site_type'           => $siteType,
				'site_name'           => get_bloginfo( 'name' ),
				'site_tagline'        => get_bloginfo( 'description' ),
				'social'              => $socialProfiles,
				'detected_plugins'    => $detectedPlugins,
				'indexing_configured' => $hasGoogleKey || $hasBingKey,
				'setup_complete'      => (bool) get_option( self::COMPLETED_OPTION ),
			)
		);
	}

	/**
	 * Complete the setup wizard and save preferences.
	 */
	public function completeSetup( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		// Save site type (affects schema output)
		if ( ! empty( $params['site_type'] ) ) {
			update_option( 'ameverywhere_site_type', sanitize_text_field( $params['site_type'] ) );
		}

		// Save social profiles
		$socialFields = array( 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube' );
		foreach ( $socialFields as $field ) {
			if ( isset( $params['social'][ $field ] ) ) {
				update_option( 'ameverywhere_social_' . $field, esc_url_raw( $params['social'][ $field ] ) );
			}
		}

		// Mark setup as complete — never show the notice again
		update_option( self::COMPLETED_OPTION, true );

		// Flush rewrite rules to ensure sitemaps work
		flush_rewrite_rules( false );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Setup complete! AmEveryWhere is fully configured.',
			)
		);
	}

	/**
	 * Detect site type from installed plugins and content patterns.
	 */
	private function detectSiteType(): string {
		// WooCommerce = eCommerce
		if ( class_exists( 'WooCommerce' ) ) {
			return 'ecommerce';
		}

		// Check for news-like publishing patterns (high post frequency)
		$recentPosts = wp_count_posts();
		$totalPosts  = $recentPosts->publish ?? 0;

		if ( $totalPosts > 100 ) {
			return 'news';
		}

		// Check for portfolio-like content
		$pages      = wp_count_posts( 'page' );
		$totalPages = $pages->publish ?? 0;

		if ( $totalPages > $totalPosts && $totalPages > 5 ) {
			return 'business';
		}

		return 'blog';
	}

	/**
	 * Try to detect social profiles from existing plugin data or theme mods.
	 */
	private function detectSocialProfiles(): array {
		$profiles = array(
			'facebook'  => '',
			'twitter'   => '',
			'instagram' => '',
			'linkedin'  => '',
			'youtube'   => '',
		);

		// Try Yoast social profiles
		$yoastSocial = get_option( 'wpseo_social', array() );
		if ( ! empty( $yoastSocial ) ) {
			$profiles['facebook']  = $yoastSocial['facebook_site'] ?? '';
			$profiles['twitter']   = $yoastSocial['twitter_site'] ?? '';
			$profiles['instagram'] = $yoastSocial['instagram_url'] ?? '';
			$profiles['linkedin']  = $yoastSocial['linkedin_url'] ?? '';
			$profiles['youtube']   = $yoastSocial['youtube_url'] ?? '';
		}

		// Try RankMath social
		$rmOptions = get_option( 'rank-math-options-titles', array() );
		if ( ! empty( $rmOptions ) ) {
			$profiles['facebook'] = $profiles['facebook'] ?: ( $rmOptions['social_url_facebook'] ?? '' );
			$profiles['twitter']  = $profiles['twitter'] ?: ( $rmOptions['twitter_author_names'] ?? '' );
		}

		// Check already-saved AmEveryWhere values
		foreach ( $profiles as $key => &$val ) {
			$existing = get_option( 'ameverywhere_social_' . $key, '' );
			if ( ! empty( $existing ) ) {
				$val = $existing;
			}
		}

		return $profiles;
	}
}
