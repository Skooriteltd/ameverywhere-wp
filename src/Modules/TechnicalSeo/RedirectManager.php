<?php

namespace AmEveryWhere\Modules\TechnicalSeo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles, matches, and performs 301/302/307 redirects.
 * Exposes a production-grade REST API for Redirect CRUD.
 *
 * Performance Architecture:
 *   - Exact matches: Direct SQL lookup by source URL (indexed column).
 *   - Regex rules: Loaded separately and cached via wp_cache (Object Cache API).
 *   - No full-table loads on the frontend — zero-bloat at scale.
 */
class RedirectManager {

	private const OPTION_KEY        = 'ameverywhere_redirects';
	private const REGEX_CACHE_KEY   = 'ameverywhere_regex_redirects';
	private const REGEX_CACHE_GROUP = 'ameverywhere';

	/**
	 * Intercept front-end requests and perform redirects if matching rule is found.
	 */
	public function handleRedirects(): void {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$currentPath  = $this->getCurrentPath();
		$cleanCurrent = $this->normalizePath( $currentPath );

		// ── 1. Fast Exact Match via direct SQL ──
		$exactMatch = $this->findExactMatch( $cleanCurrent );
		if ( $exactMatch ) {
			$this->performRedirect( $exactMatch->target, (int) $exactMatch->code );
		}

		// ── 2. Regex Match via cached rule set ──
		$regexRules = $this->getRegexRules();
		foreach ( $regexRules as $rule ) {
			$pattern = '/' . str_replace( '/', '\\/', ltrim( $rule->source, '/' ) ) . '/i';
			if ( @preg_match( $pattern, $currentPath ) ) {
				$newTarget = @preg_replace( $pattern, $rule->target, $currentPath );
				if ( $newTarget ) {
					$this->performRedirect( $newTarget, (int) $rule->code );
				}
			}
		}
	}

	/**
	 * Find an exact-match redirect by normalized source path.
	 * Uses a direct indexed query — O(1) lookup instead of loading all rows.
	 */
	private function findExactMatch( string $normalizedPath ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'ameverywhere_redirects';

		// Look up both the normalized path and common path variations
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT target, code FROM $table WHERE source = %s AND is_regex = 0 LIMIT 1",
				$normalizedPath
			)
		);

		// Also try with trailing slash stripped/added for flexibility
		if ( ! $result ) {
			$alt = ( substr( $normalizedPath, -1 ) === '/' )
				? rtrim( $normalizedPath, '/' )
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
	private function getRegexRules(): array {
		$cached = wp_cache_get( self::REGEX_CACHE_KEY, self::REGEX_CACHE_GROUP );
		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ameverywhere_redirects';

		$rules = $wpdb->get_results(
			"SELECT source, target, code FROM $table WHERE is_regex = 1"
		);

		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		// Cache for 1 hour — flushed on redirect CRUD operations
		wp_cache_set( self::REGEX_CACHE_KEY, $rules, self::REGEX_CACHE_GROUP, HOUR_IN_SECONDS );

		return $rules;
	}

	/**
	 * Invalidate the regex rules cache (called after any redirect CRUD).
	 */
	private function flushRegexCache(): void {
		wp_cache_delete( self::REGEX_CACHE_KEY, self::REGEX_CACHE_GROUP );
	}

	/**
	 * Get all redirect rules (for the admin REST API only — not used on frontend).
	 */
	public function getRedirectRules(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ameverywhere_redirects';

		$rows = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

		// Fallback and migration logic for legacy wp_options storage
		$legacyRedirects = get_option( self::OPTION_KEY );
		if ( empty( $rows ) && ! empty( $legacyRedirects ) && is_array( $legacyRedirects ) ) {
			foreach ( $legacyRedirects as $rule ) {
				$wpdb->insert(
					$table,
					array(
						'id'       => $rule['id'] ?? ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'redir_', true ) ),
						'source'   => $rule['source'] ?? '',
						'target'   => $rule['target'] ?? '',
						'code'     => intval( $rule['code'] ?? 301 ),
						'is_regex' => ! empty( $rule['is_regex'] ) ? 1 : 0,
					)
				);
			}
			$rows = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );
			delete_option( self::OPTION_KEY );
		}

		if ( empty( $rows ) ) {
			return array();
		}

		// Standardize types
		foreach ( $rows as &$row ) {
			$row['is_regex'] = ! empty( $row['is_regex'] );
			$row['code']     = intval( $row['code'] );
		}

		return $rows;
	}

	/**
	 * Expose REST routes for Redirect CRUD.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			'ameverywhere/v1',
			'/redirects',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getRedirectsEndpoint' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'addRedirectEndpoint' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/redirects/delete',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'deleteRedirectEndpoint' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// BL-005: CSV import/export
		register_rest_route(
			'ameverywhere/v1',
			'/redirects/import-csv',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'importCsvEndpoint' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/redirects/export-csv',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'exportCsvEndpoint' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);
	}

	// ── BL-005: CSV import ────────────────────────────────────────────────────

	/**
	 * Import redirects from a CSV body.
	 *
	 * The request body should be raw CSV text with a header row:
	 *   source,target,code,is_regex
	 */
	public function importCsvEndpoint( \WP_REST_Request $request ): \WP_REST_Response {
		$rawCsv = $request->get_body();

		if ( empty( $rawCsv ) ) {
			return new \WP_Error( 'empty_body', 'CSV body is empty.', array( 'status' => 400 ) );
		}

		$rows = $this->parseCsvBody( $rawCsv );

		if ( empty( $rows ) ) {
			return new \WP_Error( 'parse_error', 'Could not parse CSV. Ensure headers: source,target,code,is_regex', array( 'status' => 400 ) );
		}

		// Cap at 1000 rows
		$rows = array_slice( $rows, 0, 1000 );

		$imported = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $rows as $i => $row ) {
			$source  = sanitize_text_field( $row['source'] ?? '' );
			$target  = sanitize_text_field( $row['target'] ?? '' );
			$code    = (int) ( $row['code'] ?? 301 );
			$isRegex = ! empty( $row['is_regex'] ) && $row['is_regex'] !== '0' ? 1 : 0;

			if ( empty( $source ) || empty( $target ) ) {
				$errors[] = 'Row ' . ( $i + 2 ) . ': source and target are required.';
				++$skipped;
				continue;
			}

			if ( ! $this->isSafeTarget( $target, $isRegex ) ) {
				$errors[] = 'Row ' . ( $i + 2 ) . ': target must be a site-relative path or an absolute URL on this site.';
				++$skipped;
				continue;
			}

			if ( ! in_array( $code, array( 301, 302, 307, 410, 451 ), true ) ) {
				$errors[] = 'Row ' . ( $i + 2 ) . ": invalid code '$code'. Use 301/302/307/410/451.";
				++$skipped;
				continue;
			}

			// Skip duplicates
			global $wpdb;
			$table  = $wpdb->prefix . 'ameverywhere_redirects';
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE source = %s", $source ) );
			if ( $exists > 0 ) {
				++$skipped;
				continue;
			}

			// Run loop detection before inserting
			$loopCheck = $this->detectRedirectLoop( $source, $target );
			if ( $loopCheck !== null ) {
				$errors[] = 'Row ' . ( $i + 2 ) . ': redirect loop detected.';
				++$skipped;
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'id'       => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'redir_', true ),
					'source'   => $source,
					'target'   => $target,
					'code'     => $code,
					'is_regex' => $isRegex,
				)
			);

			++$imported;
		}

		$this->flushRegexCache();

		return rest_ensure_response(
			array(
				'success'  => true,
				'imported' => $imported,
				'skipped'  => $skipped,
				'errors'   => $errors,
			)
		);
	}

	/**
	 * Export all redirect rules as a CSV file download.
	 */
	public function exportCsvEndpoint( \WP_REST_Request $request ): \WP_REST_Response {
		$rules = $this->getRedirectRules();

		$lines   = array();
		$lines[] = 'source,target,code,is_regex';

		foreach ( $rules as $rule ) {
			$lines[] = implode(
				',',
				array(
					'"' . str_replace( '"', '""', $rule['source'] ) . '"',
					'"' . str_replace( '"', '""', $rule['target'] ) . '"',
					(int) $rule['code'],
					(int) ( $rule['is_regex'] ?? 0 ),
				)
			);
		}

		$csv = implode( "\n", $lines );

		$response = rest_ensure_response( $csv );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename=ameverywhere-redirects.csv' );

		return $response;
	}

	/**
	 * Parse raw CSV string into array of associative arrays.
	 * First row is treated as the header.
	 */
	public function parseCsvBody( string $csv ): array {
		$lines  = preg_split( '/\r\n|\r|\n/', trim( $csv ) );
		$result = array();

		if ( count( $lines ) < 2 ) {
			return array();
		}

		$header = str_getcsv( array_shift( $lines ) );
		$header = array_map( 'strtolower', array_map( 'trim', $header ) );

		// Require at minimum source and target columns
		if ( ! in_array( 'source', $header, true ) || ! in_array( 'target', $header, true ) ) {
			return array();
		}

		foreach ( $lines as $line ) {
			if ( empty( trim( $line ) ) ) {
				continue;
			}
			$values = str_getcsv( $line );
			if ( count( $values ) !== count( $header ) ) {
				continue;
			}
			$result[] = array_combine( $header, $values );
		}

		return $result;
	}

	public function checkPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function getRedirectsEndpoint( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( $this->getRedirectRules() );
	}

	public function addRedirectEndpoint( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		$source  = sanitize_text_field( $params['source'] ?? '' );
		$target  = sanitize_text_field( $params['target'] ?? '' );
		$code    = intval( $params['code'] ?? 301 );
		$isRegex = ! empty( $params['is_regex'] );

		if ( empty( $source ) || empty( $target ) ) {
			return new \WP_Error( 'invalid_fields', 'Source and Target URLs are required.', array( 'status' => 400 ) );
		}

		if ( ! $this->isSafeTarget( $target, $isRegex ) ) {
			return new \WP_Error(
				'unsafe_target',
				__( 'Redirect targets must be site-relative paths or absolute URLs on this site.', 'ameverywhere' ),
				array( 'status' => 400 )
			);
		}

		if ( ! in_array( $code, array( 301, 302, 307, 410, 451 ), true ) ) {
			$code = 301;
		}

		if ( $isRegex ) {
			$pattern = '/' . str_replace( '/', '\\/', ltrim( $source, '/' ) ) . '/i';
			if ( @preg_match( $pattern, '' ) === false ) {
				return new \WP_Error( 'invalid_regex', __( 'The provided regex pattern is invalid.', 'ameverywhere' ), array( 'status' => 400 ) );
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ameverywhere_redirects';

		// Check for duplicate source
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE source = %s", $source )
		);
		if ( $existing > 0 ) {
			return new \WP_Error( 'duplicate', 'A redirect with this source already exists.', array( 'status' => 400 ) );
		}

		// ── Redirect loop detection ──────────────────────────────────────────
		// Walk the chain: target → target's target → … up to MAX_CHAIN_DEPTH.
		// If the new source appears anywhere in that chain it would create a loop.
		$loopError = $this->detectRedirectLoop( $source, $target, $table );
		if ( $loopError !== null ) {
			return new \WP_Error( 'redirect_loop', $loopError, array( 'status' => 400 ) );
		}

		$id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'redir_', true );

		$inserted = $wpdb->insert(
			$table,
			array(
				'id'       => $id,
				'source'   => $source,
				'target'   => $target,
				'code'     => $code,
				'is_regex' => $isRegex ? 1 : 0,
			)
		);

		if ( $inserted === false ) {
			return new \WP_Error( 'db_error', 'Failed to save redirect inside the database.', array( 'status' => 500 ) );
		}

		// Flush regex cache so new rules take effect immediately
		$this->flushRegexCache();

		$updatedRedirects = $this->getRedirectRules();

		return rest_ensure_response(
			array(
				'success'   => true,
				'redirects' => $updatedRedirects,
			)
		);
	}

	/**
	 * Walk the redirect chain starting from $firstTarget and check whether
	 * $newSource appears anywhere in it (which would create a loop).
	 *
	 * Only exact-match (non-regex) rules are evaluated — regex rules are
	 * intentionally excluded because their patterns cannot be reliably
	 * compared against plain URL strings without executing the regex.
	 *
	 * @param string $newSource   The source URL being added.
	 * @param string $firstTarget The target URL the new rule points to.
	 * @param string $table       Fully-qualified DB table name.
	 * @return string|null        Error message on loop detected, null if safe.
	 */
	private function detectRedirectLoop( string $newSource, string $firstTarget, string $table ): ?string {
		global $wpdb;

		$normalizedSource = $this->normalizePath( $newSource );
		$visited          = array( $normalizedSource => true ); // treat source as already "seen"
		$current          = $this->normalizePath( $firstTarget );

		// Cap walk depth — prevents runaway DB queries on pathological chains
		$maxDepth = 10;

		for ( $depth = 0; $depth < $maxDepth; $depth++ ) {
			// Is this hop back to the source we're trying to add?
			if ( isset( $visited[ $current ] ) ) {
				return sprintf(
					'Redirect loop detected: "%s" → "%s" creates a circular chain.',
					$newSource,
					$firstTarget
				);
			}

			$visited[ $current ] = true;

			// Look up whether this URL is itself a redirect source
			$nextTarget = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT target FROM $table WHERE source = %s AND is_regex = 0 LIMIT 1",
					$current
				)
			);

			if ( empty( $nextTarget ) ) {
				break; // End of chain — no loop
			}

			$current = $this->normalizePath( $nextTarget );
		}

		return null; // Safe to insert
	}

	public function deleteRedirectEndpoint( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$id     = sanitize_text_field( $params['id'] ?? '' );

		if ( empty( $id ) ) {
			return new \WP_Error( 'missing_id', 'Redirect ID is required.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ameverywhere_redirects';

		$deleted = $wpdb->delete( $table, array( 'id' => $id ) );

		if ( $deleted === false ) {
			return new \WP_Error( 'db_error', 'Failed to delete redirect from the database.', array( 'status' => 500 ) );
		}

		// Flush regex cache
		$this->flushRegexCache();

		$updatedRedirects = $this->getRedirectRules();

		return rest_ensure_response(
			array(
				'success'   => true,
				'redirects' => $updatedRedirects,
			)
		);
	}

	/**
	 * Get the relative request path.
	 */
	private function getCurrentPath(): string {
		$path      = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$pathParts = explode( '?', $path );
		return $pathParts[0];
	}

	/**
	 * Clean and normalize a path.
	 */
	private function normalizePath( string $url ): string {
		// If absolute URL, extract path
		if ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
			$parsed = wp_parse_url( $url );
			$url    = $parsed['path'] ?? '/';
		}

		return '/' . trim( $url, '/' );
	}

	/**
	 * Redirect and exit cleanly.
	 */
	private function performRedirect( string $target, int $code ): void {
		// Support relative targets
		if ( strpos( $target, 'http://' ) !== 0 && strpos( $target, 'https://' ) !== 0 ) {
			$target = home_url( $target );
		}

		if ( ! $this->isSafeTarget( $target, false ) ) {
			return;
		}

		wp_safe_redirect( $target, $code );
		exit;
	}

	/**
	 * This plugin ships local redirects only. Restricting targets to the site
	 * host prevents an administrator typo, imported CSV, or compromised admin
	 * account from turning the site into an open redirector.
	 */
	private function isSafeTarget( string $target, bool $isRegex ): bool {
		if ( $isRegex && str_contains( $target, '$' ) ) {
			// Substitution values are evaluated at request time. Keep the
			// static prefix local; capture groups may only extend the path.
			return str_starts_with( $target, '/' );
		}

		if ( str_starts_with( $target, '/' ) && ! str_starts_with( $target, '//' ) ) {
			return true;
		}

		$parts    = wp_parse_url( $target );
		$siteHost = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_array( $parts )
			&& in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true )
			&& ! empty( $parts['host'] )
			&& strtolower( $parts['host'] ) === strtolower( (string) $siteHost );
	}
}
