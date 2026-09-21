<?php

namespace AmEveryWhere\Modules\TechnicalSeo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BrokenLinkChecker
 *
 * Scans all published post/page content for dead outbound links.
 * Batches posts across staggered WP-Cron events so it never bogs
 * down the server.
 *
 * Results table: ameverywhere_broken_links
 */
class BrokenLinkChecker {

	private const RESULTS_TABLE   = 'ameverywhere_broken_links';
	private const PROGRESS_OPTION = 'ameverywhere_blc_progress';
	private const SCAN_HOOK       = 'ameverywhere_blc_scan_batch';
	private const LOCK_OPTION     = 'ameverywhere_blc_scan_lock';
	private const BATCH_SIZE      = 10;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
		add_action( self::SCAN_HOOK, array( $this, 'processBatch' ) );
	}

	public static function createTable(): void {
		global $wpdb;
		$table   = $wpdb->prefix . self::RESULTS_TABLE;
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE IF NOT EXISTS {$table} (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id      BIGINT UNSIGNED NOT NULL,
            url          TEXT NOT NULL,
            http_code    SMALLINT UNSIGNED DEFAULT NULL,
            anchor_text  VARCHAR(255) DEFAULT '',
            last_checked DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved     TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_resolved (resolved)
        ) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function registerRoutes(): void {
		$adminCap  = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );
		$reportCap = fn() => current_user_can( 'view_seo_reports' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'ameverywhere/v1',
			'/broken-links',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getResults' ),
				'permission_callback' => $reportCap,
				'args'                => array(
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'resolved' => array( 'default' => '0' ),
				),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/broken-links/scan',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'startScan' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/broken-links/progress',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => rest_ensure_response(
					get_option(
						self::PROGRESS_OPTION,
						array(
							'status'  => 'idle',
							'scanned' => 0,
							'total'   => 0,
							'found'   => 0,
						)
					)
				),
				'permission_callback' => $reportCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/broken-links/(?P<id>\d+)/resolve',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resolveLink' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/broken-links/recheck',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'recheckLink' ),
				'permission_callback' => $adminCap,
			)
		);
	}

	public function startScan( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;
		$table = $wpdb->prefix . self::RESULTS_TABLE;

		if ( ! add_option( self::LOCK_OPTION, (string) time(), '', 'no' ) ) {
			return new \WP_Error( 'scan_in_progress', __( 'A broken-link scan is already running.', 'ameverywhere' ), array( 'status' => 409 ) );
		}

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) {
			$wpdb->query( "DELETE FROM {$table} WHERE resolved = 0" );
		}

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish'"
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		update_option(
			self::PROGRESS_OPTION,
			array(
				'status'  => 'running',
				'scanned' => 0,
				'total'   => $total,
				'found'   => 0,
				'cursor'  => 0,
				'started' => current_time( 'mysql' ),
			)
		);

		if ( $total === 0 ) {
			delete_option( self::LOCK_OPTION );
			update_option(
				self::PROGRESS_OPTION,
				array(
					'status'  => 'done',
					'scanned' => 0,
					'total'   => 0,
					'found'   => 0,
					'done_at' => current_time( 'mysql' ),
				)
			);
			return rest_ensure_response(
				array(
					'success'     => true,
					'total_posts' => 0,
					'batches'     => 0,
					'message'     => __( 'No published posts or pages to scan.', 'ameverywhere' ),
				)
			);
		}

		wp_clear_scheduled_hook( self::SCAN_HOOK );
		wp_schedule_single_event( time() + 2, self::SCAN_HOOK );

		return rest_ensure_response(
			array(
				'success'     => true,
				'total_posts' => $total,
				'batches'     => (int) ceil( $total / self::BATCH_SIZE ),
				'message'     => sprintf( /* translators: %d: Number of posts to process */ __( 'Scan started — %d posts will be processed in bounded batches.', 'ameverywhere' ), $total ),
			)
		);
	}

	public function processBatch(): void {
		global $wpdb;
		$table    = $wpdb->prefix . self::RESULTS_TABLE;
		$progress = get_option( self::PROGRESS_OPTION, array() );
		if ( ( $progress['status'] ?? '' ) !== 'running' || ! get_option( self::LOCK_OPTION ) ) {
			return;
		}

		$cursor  = max( 0, (int) ( $progress['cursor'] ?? 0 ) );
		$postIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
                 WHERE ID > %d AND post_type IN ('post', 'page') AND post_status = 'publish'
                 ORDER BY ID ASC LIMIT %d",
				$cursor,
				self::BATCH_SIZE
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $postIds ) ) {
			$this->finishScan( $progress );
			return;
		}

		$found = (int) ( $progress['found'] ?? 0 );

		foreach ( $postIds as $postId ) {
			$content = get_post_field( 'post_content', $postId );
			if ( empty( $content ) ) {
				continue;
			}

			$links = $this->extractLinks( $content );

			foreach ( $links as $link ) {
				$url    = $link['url'];
				$anchor = $link['anchor'];
				$code   = $this->checkUrl( $url );

				if ( $code === null || $code < 400 ) {
					continue;
				}

				$wpdb->insert(
					$table,
					array(
						'post_id'      => $postId,
						'url'          => $url,
						'http_code'    => $code,
						'anchor_text'  => mb_substr( $anchor, 0, 255 ),
						'last_checked' => current_time( 'mysql' ),
						'resolved'     => 0,
					)
				);
				++$found;
			}
		}

		$scanned    = (int) ( $progress['scanned'] ?? 0 ) + count( $postIds );
		$total      = (int) ( $progress['total'] ?? 0 );
		$nextCursor = (int) end( $postIds );

		update_option(
			self::PROGRESS_OPTION,
			array(
				'status'  => 'running',
				'scanned' => $scanned,
				'total'   => $total,
				'found'   => $found,
				'cursor'  => $nextCursor,
			)
		);

		wp_schedule_single_event( time() + 15, self::SCAN_HOOK );
	}

	public function getResults( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$table    = $wpdb->prefix . self::RESULTS_TABLE;
		$perPage  = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$resolved = $request->get_param( 'resolved' ) === '1' ? 1 : 0;
		$offset   = ( $page - 1 ) * $perPage;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return rest_ensure_response(
				array(
					'items' => array(),
					'total' => 0,
				)
			);
		}

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE resolved = %d",
				$resolved
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, p.post_title
             FROM {$table} b
             LEFT JOIN {$wpdb->posts} p ON p.ID = b.post_id
             WHERE b.resolved = %d
             ORDER BY b.http_code DESC, b.last_checked DESC
             LIMIT %d OFFSET %d",
				$resolved,
				$perPage,
				$offset
			),
			ARRAY_A
		);

		foreach ( $rows as &$row ) {
			$row['post_url'] = get_permalink( (int) $row['post_id'] );
			$row['edit_url'] = get_edit_post_link( (int) $row['post_id'], 'raw' );
		}

		return rest_ensure_response(
			array(
				'items' => $rows,
				'total' => $total,
			)
		);
	}

	public function resolveLink( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );
		$wpdb->update( $wpdb->prefix . self::RESULTS_TABLE, array( 'resolved' => 1 ), array( 'id' => $id ) );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function recheckLink( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;
		$params = $request->get_json_params();
		$id     = (int) ( $params['id'] ?? 0 );
		$url    = esc_url_raw( $params['url'] ?? '' );

		if ( ! $id || ! $url ) {
			return new \WP_Error( 'missing_params', 'id and url are required.', array( 'status' => 400 ) );
		}

		$code = $this->checkUrl( $url );
		$wpdb->update(
			$wpdb->prefix . self::RESULTS_TABLE,
			array(
				'http_code'    => $code,
				'last_checked' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		return rest_ensure_response(
			array(
				'success'   => true,
				'http_code' => $code,
				'broken'    => $code >= 400,
			)
		);
	}

	private function extractLinks( string $html ): array {
		$links = array();
		$dom   = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_NOERROR );
		$anchors  = $dom->getElementsByTagName( 'a' );
		$siteHost = parse_url( home_url(), PHP_URL_HOST );

		foreach ( $anchors as $a ) {
			$href = $a->getAttribute( 'href' );
			if ( empty( $href ) || ! filter_var( $href, FILTER_VALIDATE_URL ) ) {
				continue;
			}
			$host = parse_url( $href, PHP_URL_HOST );
			// Skip internal links
			if ( $host && str_contains( $siteHost ?? '', str_replace( 'www.', '', $host ) ) ) {
				continue;
			}
			$links[] = array(
				'url'    => $href,
				'anchor' => trim( $a->textContent ),
			);
		}

		return $links;
	}

	private function checkUrl( string $url ): ?int {
		static $cache = array();
		if ( isset( $cache[ $url ] ) ) {
			return $cache[ $url ];
		}

		if ( ! $this->isSafeExternalUrl( $url ) ) {
			return null;
		}

		$args = array(
			'timeout'             => 10,
			'redirection'         => 3,
			'user-agent'          => 'Mozilla/5.0 (compatible; AmEveryWhere-BLC/1.0)',
			'limit_response_size' => 1024,
		);

		$response = wp_safe_remote_head( $url, $args );
		if ( is_wp_error( $response ) ) {
			$response = wp_safe_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			$cache[ $url ] = null;
			return null;
		}

		$code          = (int) wp_remote_retrieve_response_code( $response );
		$cache[ $url ] = $code;
		return $code;
	}

	private function finishScan( array $progress ): void {
		delete_option( self::LOCK_OPTION );
		update_option(
			self::PROGRESS_OPTION,
			array(
				'status'  => 'done',
				'scanned' => (int) ( $progress['scanned'] ?? 0 ),
				'total'   => (int) ( $progress['total'] ?? 0 ),
				'found'   => (int) ( $progress['found'] ?? 0 ),
				'done_at' => current_time( 'mysql' ),
			)
		);
	}

	private function isSafeExternalUrl( string $url ): bool {
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host || strtolower( $host ) === 'localhost' ) {
			return false;
		}

		$ipAddresses = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : ( gethostbynamel( $host ) ?: array() );
		foreach ( $ipAddresses as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}
		}

		return ! empty( $ipAddresses );
	}
}
