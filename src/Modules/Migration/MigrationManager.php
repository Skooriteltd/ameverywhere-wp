<?php

namespace AmEveryWhere\Modules\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-detects installed SEO plugins and migrates their data to AmEveryWhere.
 * Supports: Yoast SEO, RankMath, All in One SEO (AIOSEO).
 *
 * Philosophy: One click, zero data loss.
 */
class MigrationManager {

	/**
	 * Meta key mapping from competitor plugins to AmEveryWhere.
	 */
	private const META_MAP = array(
		'yoast'    => array(
			'_yoast_wpseo_title'                 => '_ameverywhere_meta_title',
			'_yoast_wpseo_metadesc'              => '_ameverywhere_meta_description',
			'_yoast_wpseo_focuskw'               => '_ameverywhere_focus_keyword',
			'_yoast_wpseo_opengraph-title'       => '_ameverywhere_og_title',
			'_yoast_wpseo_opengraph-description' => '_ameverywhere_og_description',
			'_yoast_wpseo_opengraph-image'       => '_ameverywhere_og_image',
			'_yoast_wpseo_twitter-title'         => '_ameverywhere_twitter_title',
			'_yoast_wpseo_meta-robots-noindex'   => '_ameverywhere_noindex',
		),
		'rankmath' => array(
			'rank_math_title'                => '_ameverywhere_meta_title',
			'rank_math_description'          => '_ameverywhere_meta_description',
			'rank_math_focus_keyword'        => '_ameverywhere_focus_keyword',
			'rank_math_facebook_title'       => '_ameverywhere_og_title',
			'rank_math_facebook_description' => '_ameverywhere_og_description',
			'rank_math_facebook_image'       => '_ameverywhere_og_image',
			'rank_math_twitter_title'        => '_ameverywhere_twitter_title',
			'rank_math_robots'               => '_ameverywhere_noindex',
		),
		'aioseo'   => array(
			'_aioseo_title'          => '_ameverywhere_meta_title',
			'_aioseo_description'    => '_ameverywhere_meta_description',
			'_aioseo_keywords'       => '_ameverywhere_focus_keyword',
			'_aioseo_og_title'       => '_ameverywhere_og_title',
			'_aioseo_og_description' => '_ameverywhere_og_description',
			'_aioseo_og_image'       => '_ameverywhere_og_image',
			'_aioseo_twitter_title'  => '_ameverywhere_twitter_title',
		),
	);

	/**
	 * Detect which SEO plugins are currently active or have left data behind.
	 */
	public function detectPlugins(): array {
		global $wpdb;
		$detected = array();

		// Yoast SEO
		$yoastCount = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_yoast_wpseo_title' AND meta_value != ''"
		);
		if ( $yoastCount > 0 || defined( 'WPSEO_VERSION' ) ) {
			$detected[] = array(
				'id'     => 'yoast',
				'name'   => 'Yoast SEO',
				'posts'  => $yoastCount,
				'active' => defined( 'WPSEO_VERSION' ),
			);
		}

		// RankMath
		$rmCount = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'rank_math_title' AND meta_value != ''"
		);
		if ( $rmCount > 0 || defined( 'RANK_MATH_VERSION' ) ) {
			$detected[] = array(
				'id'     => 'rankmath',
				'name'   => 'Rank Math',
				'posts'  => $rmCount,
				'active' => defined( 'RANK_MATH_VERSION' ),
			);
		}

		// AIOSEO
		$aioCount = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_aioseo_title' AND meta_value != ''"
		);
		if ( $aioCount > 0 || defined( 'AIOSEO_VERSION' ) ) {
			$detected[] = array(
				'id'     => 'aioseo',
				'name'   => 'All in One SEO',
				'posts'  => $aioCount,
				'active' => defined( 'AIOSEO_VERSION' ),
			);
		}

		return $detected;
	}

	/**
	 * Run the migration for a specific plugin.
	 * Returns the number of posts migrated.
	 */
	public function migrate( string $pluginId ): array {
		if ( ! isset( self::META_MAP[ $pluginId ] ) ) {
			return array(
				'success' => false,
				'message' => 'Unknown plugin.',
			);
		}

		global $wpdb;
		$map      = self::META_MAP[ $pluginId ];
		$migrated = 0;
		$skipped  = 0;

		foreach ( $map as $sourceKey => $targetKey ) {
			// Get all posts with this source meta key that have a value
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
					$sourceKey
				)
			);

			foreach ( $rows as $row ) {
				// Don't overwrite existing AmEveryWhere data
				$existing = get_post_meta( $row->post_id, $targetKey, true );
				if ( ! empty( $existing ) ) {
					++$skipped;
					continue;
				}

				$value = $row->meta_value;

				// Special handling for noindex values
				if ( $targetKey === '_ameverywhere_noindex' ) {
					$value = $this->normalizeNoindex( $value, $pluginId );
				}

				// Special handling for RankMath focus keyword (comma-separated → first one)
				if ( $targetKey === '_ameverywhere_focus_keyword' && $pluginId === 'rankmath' ) {
					$keywords = explode( ',', $value );
					$value    = trim( $keywords[0] );
				}

				if ( ! empty( $value ) ) {
					update_post_meta( $row->post_id, $targetKey, sanitize_text_field( $value ) );
					++$migrated;
				}
			}
		}

		return array(
			'success'  => true,
			'migrated' => $migrated,
			'skipped'  => $skipped,
			'message'  => "Migrated {$migrated} meta entries. Skipped {$skipped} (already had AmEveryWhere data).",
		);
	}

	/**
	 * Normalize the noindex value from different plugins to 'yes'/'no'.
	 */
	private function normalizeNoindex( string $value, string $pluginId ): string {
		if ( $pluginId === 'yoast' ) {
			return $value === '1' ? 'yes' : 'no';
		}

		if ( $pluginId === 'rankmath' ) {
			// RankMath stores robots as a serialized array or comma-separated string
			return ( stripos( $value, 'noindex' ) !== false ) ? 'yes' : 'no';
		}

		return 'no';
	}

	/**
	 * Register REST routes for migration.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			'ameverywhere/v1',
			'/migration/detect',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handleDetect' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/migration/run',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handleMigrate' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			)
		);
	}

	public function handleDetect( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( $this->detectPlugins() );
	}

	public function handleMigrate( \WP_REST_Request $request ): \WP_REST_Response {
		$pluginId = sanitize_text_field( $request->get_param( 'plugin_id' ) );
		$result   = $this->migrate( $pluginId );
		return rest_ensure_response( $result );
	}
}
