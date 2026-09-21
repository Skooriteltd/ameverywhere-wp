<?php

namespace AmEveryWhere\Modules\TechnicalSeo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Monitors and logs front-end 404 errors safely and efficiently.
 *
 * Architecture:
 *   - Frontend: Pushes 404 details into a lightweight transient buffer (zero DB writes).
 *   - Background: A WP-Cron job flushes the buffer into the database every hour.
 *   - Limits: Caps stored logs at MAX_LOGS (100) to prevent database ballooning.
 *   - No SHOW TABLES: Table existence is checked once on activation and cached.
 */
class ErrorMonitor {

	private const OPTION_KEY          = 'ameverywhere_404_logs';
	private const BUFFER_TRANSIENT    = 'ameverywhere_404_buffer';
	private const TABLE_EXISTS_OPTION = 'ameverywhere_404_table_exists';
	private const MAX_LOGS            = 100;
	private const MAX_BUFFER          = 50; // Cap buffer size to prevent transient bloat
	private const FLUSH_HOOK          = 'ameverywhere_flush_404_buffer';

	/**
	 * Boot the error monitor — register the background flush cron.
	 */
	public function boot(): void {
		// Schedule the hourly background flush if not already scheduled
		if ( ! wp_next_scheduled( self::FLUSH_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::FLUSH_HOOK );
		}
		add_action( self::FLUSH_HOOK, array( $this, 'flushBufferToDatabase' ) );
	}

	/**
	 * Intercept front-end queries and buffer the 404 — zero database writes.
	 */
	public function log404Errors(): void {
		if ( ! is_404() ) {
			return;
		}

		// Avoid logging common static assets and hack attempts
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( empty( $uri ) || preg_match( '/\.(jpg|jpeg|png|gif|ico|css|js|map|xml|txt|woff2|woff|ttf)$/i', $uri ) ) {
			return;
		}

		// Filter out obvious bot probes
		if ( strpos( $uri, 'wp-admin' ) !== false || strpos( $uri, 'wp-login' ) !== false || strpos( $uri, '.env' ) !== false ) {
			return;
		}

		$referer   = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$userAgent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		// Push into the in-memory transient buffer (no DB write during HTTP request)
		$buffer = get_transient( self::BUFFER_TRANSIENT );
		if ( ! is_array( $buffer ) ) {
			$buffer = array();
		}

		// Deduplicate in the buffer: if the URI already exists, increment hits
		$found = false;
		foreach ( $buffer as &$entry ) {
			if ( $entry['uri'] === $uri ) {
				++$entry['hits'];
				$entry['last_hit']   = current_time( 'mysql' );
				$entry['referer']    = $referer ?: $entry['referer'];
				$entry['user_agent'] = $userAgent;
				$found               = true;
				break;
			}
		}
		unset( $entry );

		if ( ! $found && count( $buffer ) < self::MAX_BUFFER ) {
			$buffer[] = array(
				'uri'        => $uri,
				'hits'       => 1,
				'referer'    => $referer,
				'user_agent' => $userAgent,
				'last_hit'   => current_time( 'mysql' ),
			);
		}

		// Store back — short TTL, flushed by cron within the hour
		set_transient( self::BUFFER_TRANSIENT, $buffer, 2 * HOUR_IN_SECONDS );
	}

	/**
	 * Background cron callback: flush the transient buffer into the database.
	 * Runs outside the HTTP request lifecycle — safe for batch DB writes.
	 */
	public function flushBufferToDatabase(): void {
		$buffer = get_transient( self::BUFFER_TRANSIENT );
		if ( empty( $buffer ) || ! is_array( $buffer ) ) {
			return;
		}

		// Check table existence from cached option (set during activation)
		if ( get_option( self::TABLE_EXISTS_OPTION ) !== 'yes' ) {
			// Re-verify once and cache the result
			global $wpdb;
			$table = $wpdb->prefix . 'ameverywhere_404_logs';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				update_option( self::TABLE_EXISTS_OPTION, 'yes', false );
			} else {
				return; // Table doesn't exist, skip
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ameverywhere_404_logs';

		foreach ( $buffer as $entry ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE uri = %s", $entry['uri'] ) );

			if ( $existing ) {
				$wpdb->update(
					$table,
					array(
						'hits'       => intval( $existing->hits ) + intval( $entry['hits'] ),
						'last_hit'   => $entry['last_hit'],
						'referer'    => $entry['referer'] ?: $existing->referer,
						'user_agent' => $entry['user_agent'],
					),
					array( 'id' => $existing->id )
				);
			} else {
				$wpdb->insert(
					$table,
					array(
						'id'         => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'err_404_', true ),
						'uri'        => $entry['uri'],
						'hits'       => intval( $entry['hits'] ),
						'referer'    => $entry['referer'],
						'user_agent' => $entry['user_agent'],
						'last_hit'   => $entry['last_hit'],
					)
				);
			}
		}

		// Trim oldest logs if total exceeds MAX_LOGS
		$totalLogs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $totalLogs > self::MAX_LOGS ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM {$table} ORDER BY last_hit DESC LIMIT %d
                        ) as temp
                    )",
					self::MAX_LOGS
				)
			);
		}

		// Clear the buffer after successful flush
		delete_transient( self::BUFFER_TRANSIENT );
	}

	/**
	 * Mark the 404 table as existing (called from Installer on activation).
	 */
	public static function markTableExists(): void {
		update_option( self::TABLE_EXISTS_OPTION, 'yes', false );
	}

	/**
	 * Expose REST routes for 404 monitoring.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			'ameverywhere/v1',
			'/errors/404',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get404LogsEndpoint' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clear404LogsEndpoint' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		// BL-001: one-click redirect creation from a 404 log entry
		register_rest_route(
			'ameverywhere/v1',
			'/errors/404/create-redirect',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'createRedirectFromLog' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);
	}

	/**
	 * BL-001: Create a redirect rule from an existing 404 log entry.
	 *
	 * Payload: { id: string, target: string, code?: int }
	 */
	public function createRedirectFromLog( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$id     = sanitize_text_field( $params['id'] ?? '' );
		$target = sanitize_text_field( $params['target'] ?? '' );
		$code   = in_array( (int) ( $params['code'] ?? 301 ), array( 301, 302, 307, 410, 451 ), true )
			? (int) $params['code']
			: 301;

		if ( empty( $id ) || empty( $target ) ) {
			return new \WP_Error( 'missing_params', 'id and target are required.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$logTable = $wpdb->prefix . 'ameverywhere_404_logs';

		// Fetch the log entry to get the source URI
		$entry = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $logTable WHERE id = %s", $id ),
			ARRAY_A
		);

		if ( empty( $entry ) ) {
			return new \WP_Error( 'not_found', '404 log entry not found.', array( 'status' => 404 ) );
		}

		$source = $entry['uri'];

		// Insert into redirect table
		$redirectTable = $wpdb->prefix . 'ameverywhere_redirects';
		$redirectId    = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'redir_', true );

		// Check for duplicate
		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $redirectTable WHERE source = %s", $source )
		);
		if ( (int) $exists > 0 ) {
			return new \WP_Error( 'duplicate', 'A redirect for this source already exists.', array( 'status' => 400 ) );
		}

		$inserted = $wpdb->insert(
			$redirectTable,
			array(
				'id'       => $redirectId,
				'source'   => $source,
				'target'   => $target,
				'code'     => $code,
				'is_regex' => 0,
			)
		);

		if ( $inserted === false ) {
			return new \WP_Error( 'db_error', 'Failed to create redirect.', array( 'status' => 500 ) );
		}

		// Mark 404 log entry as resolved
		$wpdb->update( $logTable, array( 'resolved' => 1 ), array( 'id' => $id ) );

		// Flush regex cache so new rule is live
		wp_cache_delete( 'ameverywhere_regex_redirects', 'ameverywhere' );

		return rest_ensure_response(
			array(
				'success'  => true,
				'redirect' => array(
					'source' => $source,
					'target' => $target,
					'code'   => $code,
				),
			)
		);
	}

	public function checkPermission(): bool {
		return current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );
	}

	public function get404LogsEndpoint( \WP_REST_Request $request ): \WP_REST_Response {
		// Force-flush any pending buffer so admin sees the latest data
		$this->flushBufferToDatabase();

		global $wpdb;
		$table = $wpdb->prefix . 'ameverywhere_404_logs';

		if ( get_option( self::TABLE_EXISTS_OPTION ) !== 'yes' ) {
			return rest_ensure_response( array() );
		}

		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY last_hit DESC", ARRAY_A );

		// Run legacy migration on the first load if table is empty but options has logs
		$legacyLogs = get_option( self::OPTION_KEY );
		if ( empty( $rows ) && ! empty( $legacyLogs ) && is_array( $legacyLogs ) ) {
			foreach ( $legacyLogs as $log ) {
				$wpdb->insert(
					$table,
					array(
						'id'         => $log['id'] ?? ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'err_404_', true ) ),
						'uri'        => $log['uri'] ?? '',
						'hits'       => intval( $log['hits'] ?? 1 ),
						'referer'    => $log['referer'] ?? '',
						'user_agent' => $log['user_agent'] ?? '',
						'last_hit'   => $log['last_hit'] ?? current_time( 'mysql' ),
					)
				);
			}
			$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY last_hit DESC", ARRAY_A );
			delete_option( self::OPTION_KEY );
		}

		// Standardize types
		if ( ! empty( $rows ) ) {
			foreach ( $rows as &$row ) {
				$row['hits'] = intval( $row['hits'] );
			}
		} else {
			$rows = array();
		}

		return rest_ensure_response( $rows );
	}

	public function clear404LogsEndpoint( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$id     = sanitize_text_field( $params['id'] ?? '' );

		global $wpdb;
		$table = $wpdb->prefix . 'ameverywhere_404_logs';

		if ( empty( $id ) ) {
			// Clear all logs
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE 1 = %d", 1 ) );
			return rest_ensure_response(
				array(
					'success' => true,
					'logs'    => array(),
				)
			);
		}

		// Clear specific log item
		$wpdb->delete( $table, array( 'id' => $id ) );

		$updatedLogs = $wpdb->get_results( "SELECT * FROM $table ORDER BY last_hit DESC", ARRAY_A );
		if ( ! empty( $updatedLogs ) ) {
			foreach ( $updatedLogs as &$row ) {
				$row['hits'] = intval( $row['hits'] );
			}
		} else {
			$updatedLogs = array();
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'logs'    => $updatedLogs,
			)
		);
	}

	/**
	 * Cleanup on plugin deactivation: clear the scheduled flush event.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( self::FLUSH_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::FLUSH_HOOK );
		}
	}
}
