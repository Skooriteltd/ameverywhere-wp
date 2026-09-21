<?php

namespace AmEveryWhere\Modules\Indexing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BingIndexNowApi
 *
 * Submits URLs to the IndexNow protocol (supported by Bing, Yandex, etc.)
 * for near-instant crawl notification.
 *
 * BL-021 enhancements:
 * - Serves the /{key}.txt verification file virtually via `init` hook
 * - Logs every submission (last 100 entries)
 * - Auto-submits on post publish/update (transition_post_status)
 * - REST endpoint for viewing submission log
 */
class BingIndexNowApi {

	private const LOG_OPTION = 'ameverywhere_indexnow_log';
	private const LOG_MAX    = 100;
	private const KEY_OPTION = 'ameverywhere_indexnow_key';
	private const ENDPOINT   = 'https://api.indexnow.org/indexnow';

	public function register(): void {
		add_action( 'init', array( $this, 'serveKeyFile' ) );
		add_action( 'transition_post_status', array( $this, 'autoSubmitOnPublish' ), 10, 3 );
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	// ── Key file virtual serving ──────────────────────────────────────────────

	/**
	 * Serve the IndexNow key verification file: GET /{key}.txt
	 */
	public function serveKeyFile(): void {
		$apiKey = get_option( self::KEY_OPTION, '' );
		if ( empty( $apiKey ) ) {
			return;
		}

		$requestUri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$keyFile    = '/' . $apiKey . '.txt';

		if ( $requestUri !== $keyFile ) {
			return;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo esc_html( $apiKey );
		exit;
	}

	// ── Auto-submit on publish ────────────────────────────────────────────────

	/**
	 * Automatically ping IndexNow when a post is published or re-published.
	 */
	public function autoSubmitOnPublish( string $newStatus, string $oldStatus, \WP_Post $post ): void {
		if ( $newStatus !== 'publish' ) {
			return;
		}

		// Only for public post types
		$postTypeObj = get_post_type_object( $post->post_type );
		if ( ! $postTypeObj || ! $postTypeObj->public ) {
			return;
		}

		$url = get_permalink( $post->ID );
		if ( $url ) {
			$this->ping( $url );
		}
	}

	// ── Core ping ─────────────────────────────────────────────────────────────

	public function ping( string $url ): bool {
		$apiKey = get_option( self::KEY_OPTION, '' );
		$host   = parse_url( home_url(), PHP_URL_HOST );

		if ( empty( $apiKey ) || empty( $host ) ) {
			return false;
		}

		$body = wp_json_encode(
			array(
				'host'        => $host,
				'key'         => $apiKey,
				'keyLocation' => home_url( "/{$apiKey}.txt" ),
				'urlList'     => array( $url ),
			)
		);

		$response = wp_safe_remote_post(
			self::ENDPOINT,
			array(
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => $body,
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logSubmission( $url, 0 );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$this->logSubmission( $url, $code );

		return $code === 200 || $code === 202;
	}

	// ── Submission log ────────────────────────────────────────────────────────

	public function logSubmission( string $url, int $statusCode ): void {
		$log = $this->getSubmissionLog();

		array_unshift(
			$log,
			array(
				'url'          => $url,
				'submitted_at' => current_time( 'mysql' ),
				'status_code'  => $statusCode,
			)
		);

		$log = array_slice( $log, 0, self::LOG_MAX );
		update_option( self::LOG_OPTION, $log, false );
	}

	public function getSubmissionLog(): array {
		return (array) get_option( self::LOG_OPTION, array() );
	}

	// ── REST routes ───────────────────────────────────────────────────────────

	public function registerRoutes(): void {
		register_rest_route(
			'ameverywhere/v1',
			'/indexing/indexnow-log',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => rest_ensure_response( $this->getSubmissionLog() ),
				'permission_callback' => fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/indexing/indexnow-submit',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'manualSubmit' ),
				'permission_callback' => fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' ),
			)
		);
	}

	public function manualSubmit( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$url    = sanitize_url( $params['url'] ?? '' );
		$postId = absint( $params['post_id'] ?? 0 );

		if ( $postId > 0 ) {
			$url = get_permalink( $postId );
		}

		if ( empty( $url ) ) {
			return new \WP_Error( 'missing_url', 'Provide url or post_id.', array( 'status' => 400 ) );
		}

		$success = $this->ping( $url );

		return rest_ensure_response(
			array(
				'success' => $success,
				'url'     => $url,
				'log'     => array_slice( $this->getSubmissionLog(), 0, 5 ),
			)
		);
	}
}
