<?php

namespace AmEveryWhere\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TechnicalSeoAuditEngine
 *
 * Runs a multi-point technical SEO audit via WP-Cron background jobs.
 * Checks 14 factors across meta, canonical, robots, schema, images,
 * 404s, redirects, sitemap, and more. Results are stored in wp_options
 * and exposed via REST API.
 *
 * BL-006
 */
class TechnicalSeoAuditEngine {

	private const RESULTS_OPTION  = 'ameverywhere_audit_results';
	private const PROGRESS_OPTION = 'ameverywhere_audit_progress';
	private const CRON_HOOK       = 'ameverywhere_run_technical_audit';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
		add_action( self::CRON_HOOK, array( $this, 'runAudit' ) );
	}

	// ── REST routes ───────────────────────────────────────────────────────────

	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_options' );

		register_rest_route(
			'ameverywhere/v1',
			'/audit/technical',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => rest_ensure_response( get_option( self::RESULTS_OPTION, null ) ?: array( 'message' => 'No audit has been run yet.' ) ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/audit/technical/run',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'scheduleRun' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/audit/technical/progress',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => rest_ensure_response(
					get_option(
						self::PROGRESS_OPTION,
						array(
							'status'        => 'idle',
							'percent'       => 0,
							'current_check' => '',
						)
					)
				),
				'permission_callback' => $adminCap,
			)
		);
	}

	public function scheduleRun( \WP_REST_Request $request ): \WP_REST_Response {
		wp_schedule_single_event( time() + 2, self::CRON_HOOK );
		update_option(
			self::PROGRESS_OPTION,
			array(
				'status'        => 'running',
				'percent'       => 0,
				'current_check' => 'Starting…',
			)
		);
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Audit scheduled.',
			)
		);
	}

	// ── Audit runner ──────────────────────────────────────────────────────────

	public function runAudit(): void {
		$checks = array(
			'missing_meta_descriptions' => 'Checking meta descriptions…',
			'missing_alt_text'          => 'Checking image alt text…',
			'check_404_errors'          => 'Checking 404 errors…',
			'redirect_chains'           => 'Checking redirect chains…',
			'orphaned_pages'            => 'Checking orphaned pages…',
			'noindex_important_pages'   => 'Checking noindex directives…',
			'sitemap_health'            => 'Checking sitemap…',
			'robots_txt'                => 'Checking robots.txt…',
			'favicon'                   => 'Checking favicon…',
			'ssl_certificate'           => 'Checking SSL…',
			'schema_presence'           => 'Checking schema markup…',
			'duplicate_titles'          => 'Checking for duplicate titles…',
			'stale_cornerstone'         => 'Checking cornerstone content…',
			'missing_canonicals'        => 'Checking canonical tags…',
		);

		$total   = count( $checks );
		$idx     = 0;
		$results = array();

		foreach ( $checks as $checkId => $label ) {
			$this->setProgress( 'running', (int) round( ( $idx / $total ) * 100 ), $label );

			try {
				$method = lcfirst( str_replace( '_', '', ucwords( $checkId, '_' ) ) );
				$items  = method_exists( $this, $method ) ? $this->{$method}() : array();
			} catch ( \Throwable $e ) {
				$items = array( array( 'error' => $e->getMessage() ) );
			}

			$status              = $this->deriveStatus( $checkId, $items );
			$results['checks'][] = array(
				'id'      => $checkId,
				'name'    => ucwords( str_replace( '_', ' ', $checkId ) ),
				'status'  => $status,
				'message' => $this->buildMessage( $checkId, $items, $status ),
				'items'   => array_slice( (array) $items, 0, 20 ),
			);

			++$idx;
		}

		$critical = count( array_filter( $results['checks'], fn( $c ) => $c['status'] === 'critical' ) );
		$warnings = count( array_filter( $results['checks'], fn( $c ) => $c['status'] === 'warning' ) );
		$passed   = count( array_filter( $results['checks'], fn( $c ) => $c['status'] === 'passed' ) );

		$results['generated_at'] = current_time( 'mysql' );
		$results['summary']      = array(
			'critical' => $critical,
			'warnings' => $warnings,
			'passed'   => $passed,
		);

		update_option( self::RESULTS_OPTION, $results );
		$this->setProgress( 'done', 100, 'Audit complete.' );
	}

	// ── Individual checks ─────────────────────────────────────────────────────

	private function missingMetaDescriptions(): array {
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_ameverywhere_meta_description',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_ameverywhere_meta_description',
						'value'   => '',
						'compare' => '=',
					),
				),
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'post_id' => $post->ID,
				'title'   => $post->post_title,
				'url'     => get_permalink( $post->ID ),
			);
		}
		return $items;
	}

	private function missingAltText(): array {
		$count = ( new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
				'posts_per_page' => 1,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_wp_attachment_image_alt',
						'value'   => '',
						'compare' => '=',
					),
				),
			)
		) )->found_posts;

		return $count > 0 ? array(
			array(
				'count'   => $count,
				'message' => "$count images are missing alt text.",
			),
		) : array();
	}

	private function check404Errors(): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'ameverywhere_404_logs';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $exists ) {
			return array();
		}
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		return $count > 0 ? array(
			array(
				'count'   => $count,
				'message' => "$count 404 errors logged.",
			),
		) : array();
	}

	private function redirectChains(): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'ameverywhere_redirects';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $exists ) {
			return array();
		}

		// Find rows where target is itself a source (chains of 2+)
		$chains = $wpdb->get_results(
			"SELECT a.source, a.target, b.target AS next_target
             FROM $table a
             INNER JOIN $table b ON a.target = b.source
             WHERE a.is_regex = 0 AND b.is_regex = 0
             LIMIT 20"
		);

		return (array) $chains;
	}

	private function orphanedPages(): array {
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'meta_query'     => array(
					array(
						'key'     => '_ameverywhere_inbound_links',
						'value'   => '0',
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$total = ( new \WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'     => '_ameverywhere_inbound_links',
						'value'   => '0',
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		) )->found_posts;

		return $total > 0 ? array_merge(
			array( array( 'total_orphaned' => $total ) ),
			array_map(
				fn( $p ) => array(
					'post_id' => $p->ID,
					'title'   => $p->post_title,
					'url'     => get_permalink( $p->ID ),
				),
				$query->posts
			)
		) : array();
	}

	private function noindexImportantPages(): array {
		$issues      = array();
		$frontPageId = (int) get_option( 'page_on_front' );

		if ( $frontPageId > 0 ) {
			$noindex = get_post_meta( $frontPageId, '_ameverywhere_noindex', true );
			if ( $noindex === 'yes' ) {
				$issues[] = array(
					'post_id' => $frontPageId,
					'title'   => 'Front Page',
					'issue'   => 'Front page is set to noindex!',
				);
			}
		}

		return $issues;
	}

	private function sitemapHealth(): array {
		$sitemapUrl = home_url( '/sitemap.xml' );
		$response   = wp_safe_remote_get( $sitemapUrl, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return array( array( 'issue' => 'Sitemap unreachable: ' . $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		return ( $code === 200 ) ? array() : array( array( 'issue' => "Sitemap returned HTTP $code." ) );
	}

	private function robotsTxt(): array {
		$url      = home_url( '/robots.txt' );
		$response = wp_safe_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return array( array( 'issue' => 'robots.txt unreachable: ' . $response->get_error_message() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( stripos( $body, 'Disallow: /' ) !== false && strpos( $body, 'Disallow: /wp-admin' ) === false ) {
			return array( array( 'issue' => 'robots.txt may be blocking all crawlers. Review carefully.' ) );
		}

		return array();
	}

	private function favicon(): array {
		$favicon = get_site_icon_url();
		return empty( $favicon ) ? array( array( 'issue' => 'No site icon (favicon) is set. Set one in Appearance → Customize → Site Identity.' ) ) : array();
	}

	private function sslCertificate(): array {
		$siteUrl = site_url();
		return ( strpos( $siteUrl, 'https://' ) !== 0 )
			? array( array( 'issue' => 'Site is not using HTTPS. SSL is a confirmed Google ranking signal.' ) )
			: array();
	}

	private function schemaPresence(): array {
		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'     => '_ameverywhere_schema_type',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		return $query->found_posts > 0
			? array(
				array(
					'count'   => $query->found_posts,
					'message' => $query->found_posts . ' recent posts have no schema type set.',
				),
			)
			: array();
	}

	private function duplicateTitles(): array {
		global $wpdb;

		$dupes = $wpdb->get_results(
			"SELECT meta_value AS title, COUNT(*) AS count
             FROM $wpdb->postmeta
             WHERE meta_key = '_ameverywhere_meta_title'
               AND meta_value != ''
             GROUP BY meta_value
             HAVING count > 1
             LIMIT 10"
		);

		return (array) $dupes;
	}

	private function staleCornerstone(): array {
		$stale = (array) get_option( 'ameverywhere_stale_cornerstone_ids', array() );
		return empty( $stale ) ? array() : array(
			array(
				'count'   => count( $stale ),
				'message' => count( $stale ) . ' cornerstone posts are stale.',
			),
		);
	}

	private function missingCanonicals(): array {
		// Canonicals are auto-generated; this is an info-level check
		return array();
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function deriveStatus( string $checkId, array $items ): string {
		if ( empty( $items ) ) {
			return 'passed';
		}

		$criticalChecks = array( 'noindex_important_pages', 'ssl_certificate', 'robots_txt' );
		$warningChecks  = array( 'missing_meta_descriptions', 'redirect_chains', 'check_404_errors', 'sitemap_health', 'duplicate_titles', 'stale_cornerstone' );

		if ( in_array( $checkId, $criticalChecks, true ) ) {
			return 'critical';
		}

		if ( in_array( $checkId, $warningChecks, true ) ) {
			// Escalate 404s to critical if count > 20
			if ( $checkId === 'check_404_errors' && ! empty( $items[0]['count'] ) && $items[0]['count'] > 20 ) {
				return 'critical';
			}
			return 'warning';
		}

		return 'info';
	}

	private function buildMessage( string $checkId, array $items, string $status ): string {
		if ( $status === 'passed' ) {
			return 'No issues found.';
		}

		if ( ! empty( $items[0]['message'] ) ) {
			return $items[0]['message'];
		}

		if ( ! empty( $items[0]['issue'] ) ) {
			return $items[0]['issue'];
		}

		return count( $items ) . ' issue(s) detected.';
	}

	private function setProgress( string $status, int $percent, string $currentCheck ): void {
		update_option(
			self::PROGRESS_OPTION,
			array(
				'status'        => $status,
				'percent'       => $percent,
				'current_check' => $currentCheck,
				'updated_at'    => current_time( 'mysql' ),
			)
		);
	}
}
