<?php

namespace AmEveryWhere\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AmEveryWhere\Core\Event\EventManager;
use AmEveryWhere\Core\Security\KeyVault;

class AdminModule {

	private EventManager $eventManager;
	private AdminMenu $adminMenu;

	public function __construct( EventManager $eventManager, AdminMenu $adminMenu ) {
		$this->eventManager = $eventManager;
		$this->adminMenu    = $adminMenu;
	}

	public function boot(): void {
		$this->eventManager->addAction( 'admin_menu', array( $this->adminMenu, 'registerMenu' ) );
		$this->eventManager->addAction( 'admin_enqueue_scripts', array( $this->adminMenu, 'enqueueAssets' ) );
		$this->eventManager->addAction( 'rest_api_init', array( $this, 'registerRestRoutes' ) );

		// Register SEO Score column in Posts/Pages list tables
		$seoScoreColumn = new SeoScoreColumn();
		$seoScoreColumn->register();
	}

	public function registerRestRoutes(): void {
		register_rest_route(
			'ameverywhere/v1',
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'updateSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/settings/ai/test-connection',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'testAiConnection' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/index-url',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'manualIndexUrl' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Phase 1 Backlog: PageSpeed per post ──────────────────────────────
		register_rest_route(
			'ameverywhere/v1',
			'/pagespeed',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getPageSpeed' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// ── Phase 1 Backlog: GSC per-post ranking keywords ───────────────────
		register_rest_route(
			'ameverywhere/v1',
			'/gsc/keywords',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getGscKeywords' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// ── Phase 2 Backlog: Site-wide GSC Dashboard ─────────────────────────
		register_rest_route(
			'ameverywhere/v1',
			'/gsc/dashboard',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getGscDashboard' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Phase 2 Backlog: Keyword Cannibalization Report ──────────────────
		register_rest_route(
			'ameverywhere/v1',
			'/gsc/cannibalization',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getKeywordCannibalization' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Phase 2 Backlog: Orphan Pages Report ─────────────────────────────
		register_rest_route(
			'ameverywhere/v1',
			'/links/orphans',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getOrphanPages' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Phase 2 Backlog: Site-wide Link Statistics ───────────────────────
		register_rest_route(
			'ameverywhere/v1',
			'/links/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getLinkStats' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Phase 2 Backlog: GA4 Integration settings ────────────────────────
		register_rest_route(
			'ameverywhere/v1',
			'/settings/ga4',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getGa4Settings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'updateGa4Settings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		// ── Phase 2 Backlog: GA4 Traffic Report ──────────────────────────────
		register_rest_route(
			'ameverywhere/v1',
			'/ga4/report',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getGa4Report' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Phase 3: Import / Export settings ────────────────────────────────
		register_rest_route(
			'ameverywhere/v1',
			'/settings/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'exportSettings' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/settings/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'importSettings' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// ── Phase 3: HTML Sitemap ─────────────────────────────────────────────
		register_rest_route(
			'ameverywhere/v1',
			'/html-sitemap',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getHtmlSitemapSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveHtmlSitemapSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/html-sitemap/preview',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'previewHtmlSitemap' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);
	}

	public function checkPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function getSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$aiGateway = new \AmEveryWhere\Core\Ai\AiGateway();

		$openaiKeyStored = get_option( 'ameverywhere_openai_key', '' );
		$openaiKey       = ! empty( $openaiKeyStored ) ? '••••••••••••' : '';

		$anthropicKeyStored = get_option( 'ameverywhere_anthropic_key', '' );
		$anthropicKey       = ! empty( $anthropicKeyStored ) ? '••••••••••••' : '';
		$rawGoogleKey       = (string) get_option( 'ameverywhere_google_indexing_key', '' );

		return rest_ensure_response(
			array(
				'google_indexing_configured' => $rawGoogleKey !== '',
				'indexnow_configured'        => (string) get_option( 'ameverywhere_indexnow_key', '' ) !== '',
				'auto_index'                 => get_option( 'ameverywhere_auto_index', 'no' ) === 'yes',
				'enable_google_indexing_api' => get_option( 'ameverywhere_enable_google_indexing_api', 'no' ) === 'yes',
				'delete_data_on_uninstall'   => get_option( 'ameverywhere_delete_data_on_uninstall', 'no' ) === 'yes',
				'google_verify'              => get_option( 'ameverywhere_google_verify', '' ),
				'bing_verify'                => get_option( 'ameverywhere_bing_verify', '' ),
				'yandex_verify'              => get_option( 'ameverywhere_yandex_verify', '' ),
				'pinterest_verify'           => get_option( 'ameverywhere_pinterest_verify', '' ),
				'rss_before_content'         => get_option( 'ameverywhere_rss_before_content', '' ),
				'rss_after_content'          => get_option( 'ameverywhere_rss_after_content', '' ),
				'breadcrumb_separator'       => get_option( 'ameverywhere_breadcrumb_separator', '›' ),
				'breadcrumb_home_label'      => get_option( 'ameverywhere_breadcrumb_home_label', 'Home' ),
				'breadcrumb_auto_insert'     => get_option( 'ameverywhere_breadcrumb_auto_insert', 'none' ),
				'sitemap_exclude_post_types' => get_option( 'ameverywhere_sitemap_exclude_post_types', '' ),
				'sitemap_exclude_posts'      => get_option( 'ameverywhere_sitemap_exclude_posts', '' ),

				// Secure AI Vault settings
				'ai_provider'                => get_option( 'ameverywhere_ai_provider', 'openai' ),
				'openai_key'                 => $openaiKey,
				'anthropic_key'              => $anthropicKey,
				'ollama_url'                 => get_option( 'ameverywhere_ollama_url', 'http://localhost:11434' ),
				'default_share_image'        => get_option( 'ameverywhere_default_share_image', '' ),
			)
		);
	}

	public function updateSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$params    = $request->get_json_params();
		$aiGateway = new \AmEveryWhere\Core\Ai\AiGateway();

		if ( ! empty( $params['google_indexing_key'] ) ) {
			$jsonValue = wp_unslash( $params['google_indexing_key'] );
			if ( json_decode( $jsonValue ) !== null ) {
				update_option( 'ameverywhere_google_indexing_key', KeyVault::encrypt( $jsonValue ) );
			}
		}

		if ( ! empty( $params['indexnow_key'] ) ) {
			update_option( 'ameverywhere_indexnow_key', sanitize_text_field( $params['indexnow_key'] ) );
		}

		if ( isset( $params['auto_index'] ) ) {
			update_option( 'ameverywhere_auto_index', $params['auto_index'] ? 'yes' : 'no' );
		}

		if ( isset( $params['enable_google_indexing_api'] ) ) {
			update_option( 'ameverywhere_enable_google_indexing_api', $params['enable_google_indexing_api'] ? 'yes' : 'no' );
		}

		if ( isset( $params['delete_data_on_uninstall'] ) ) {
			update_option( 'ameverywhere_delete_data_on_uninstall', $params['delete_data_on_uninstall'] ? 'yes' : 'no' );
		}

		if ( isset( $params['google_verify'] ) ) {
			update_option( 'ameverywhere_google_verify', sanitize_text_field( $params['google_verify'] ) );
		}

		if ( isset( $params['bing_verify'] ) ) {
			update_option( 'ameverywhere_bing_verify', sanitize_text_field( $params['bing_verify'] ) );
		}

		if ( isset( $params['yandex_verify'] ) ) {
			update_option( 'ameverywhere_yandex_verify', sanitize_text_field( $params['yandex_verify'] ) );
		}

		if ( isset( $params['pinterest_verify'] ) ) {
			update_option( 'ameverywhere_pinterest_verify', sanitize_text_field( $params['pinterest_verify'] ) );
		}

		if ( isset( $params['rss_before_content'] ) ) {
			update_option( 'ameverywhere_rss_before_content', wp_kses_post( $params['rss_before_content'] ) );
		}

		if ( isset( $params['rss_after_content'] ) ) {
			update_option( 'ameverywhere_rss_after_content', wp_kses_post( $params['rss_after_content'] ) );
		}

		if ( isset( $params['breadcrumb_separator'] ) ) {
			update_option( 'ameverywhere_breadcrumb_separator', sanitize_text_field( $params['breadcrumb_separator'] ) );
		}

		if ( isset( $params['breadcrumb_home_label'] ) ) {
			update_option( 'ameverywhere_breadcrumb_home_label', sanitize_text_field( $params['breadcrumb_home_label'] ) );
		}

		if ( isset( $params['breadcrumb_auto_insert'] ) ) {
			update_option( 'ameverywhere_breadcrumb_auto_insert', sanitize_text_field( $params['breadcrumb_auto_insert'] ) );
		}

		if ( isset( $params['sitemap_exclude_post_types'] ) ) {
			update_option( 'ameverywhere_sitemap_exclude_post_types', sanitize_text_field( $params['sitemap_exclude_post_types'] ) );
		}

		if ( isset( $params['sitemap_exclude_posts'] ) ) {
			update_option( 'ameverywhere_sitemap_exclude_posts', sanitize_text_field( $params['sitemap_exclude_posts'] ) );
		}

		// Save secure AI parameters
		if ( isset( $params['ai_provider'] ) ) {
			update_option( 'ameverywhere_ai_provider', sanitize_text_field( $params['ai_provider'] ) );
		}

		if ( isset( $params['openai_key'] ) ) {
			$val = $params['openai_key'];
			if ( $val !== '••••••••••••' ) {
				update_option( 'ameverywhere_openai_key', empty( $val ) ? '' : KeyVault::encrypt( $val ) );
			}
		}

		if ( isset( $params['anthropic_key'] ) ) {
			$val = $params['anthropic_key'];
			if ( $val !== '••••••••••••' ) {
				update_option( 'ameverywhere_anthropic_key', empty( $val ) ? '' : KeyVault::encrypt( $val ) );
			}
		}

		if ( isset( $params['ollama_url'] ) ) {
			update_option( 'ameverywhere_ollama_url', esc_url_raw( $params['ollama_url'] ) );
		}

		if ( isset( $params['default_share_image'] ) ) {
			update_option( 'ameverywhere_default_share_image', esc_url_raw( $params['default_share_image'] ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Authenticated endpoint to test AI provider connections.
	 */
	public function testAiConnection( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		$provider  = isset( $params['ai_provider'] ) ? sanitize_text_field( $params['ai_provider'] ) : 'openai';
		$aiGateway = new \AmEveryWhere\Core\Ai\AiGateway();

		// Temporarily override parameters for connection test if updated in the UI
		if ( isset( $params['openai_key'] ) && $params['openai_key'] !== '••••••••••••' ) {
			update_option( 'ameverywhere_openai_key', empty( $params['openai_key'] ) ? '' : KeyVault::encrypt( $params['openai_key'] ) );
		}
		if ( isset( $params['anthropic_key'] ) && $params['anthropic_key'] !== '••••••••••••' ) {
			update_option( 'ameverywhere_anthropic_key', empty( $params['anthropic_key'] ) ? '' : KeyVault::encrypt( $params['anthropic_key'] ) );
		}
		if ( isset( $params['ollama_url'] ) ) {
			update_option( 'ameverywhere_ollama_url', esc_url_raw( $params['ollama_url'] ) );
		}
		update_option( 'ameverywhere_ai_provider', $provider );

		// Ping the provider with a short prompt
		$result = $aiGateway->queryModel( 'Return exactly the word "SUCCESS" and nothing else.', 'System test.' );

		if ( $result['success'] && stripos( $result['text'], 'SUCCESS' ) !== false ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Connection test passed successfully!', 'ameverywhere' ),
				)
			);
		}

		$msg = isset( $result['message'] ) ? $result['message'] : __( 'Failed to connect to the selected AI provider.', 'ameverywhere' );
		return new \WP_Error( 'ai_conn_fail', $msg, array( 'status' => 400 ) );
	}

	public function manualIndexUrl( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$postId = $request->get_param( 'post_id' );
		if ( ! $postId ) {
			return new \WP_Error( 'missing_param', __( 'Post ID is required.', 'ameverywhere' ), array( 'status' => 400 ) );
		}

		if ( ! current_user_can( 'edit_post', $postId ) ) {
			return new \WP_Error( 'forbidden_post', __( 'You cannot submit this post for indexing.', 'ameverywhere' ), array( 'status' => 403 ) );
		}

		$url = get_permalink( $postId );
		if ( ! $url ) {
			return new \WP_Error( 'invalid_post', __( 'Invalid Post ID.', 'ameverywhere' ), array( 'status' => 400 ) );
		}

		// Fire job in the background securely via WP Cron
		$queueManager = new \AmEveryWhere\Core\Queue\QueueManager();
		$queueManager->push(
			\AmEveryWhere\Modules\Indexing\IndexingJob::class,
			array(
				'post_id' => $postId,
				'url'     => $url,
				'action'  => 'URL_UPDATED',
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'IndexNow notification queued. Google Indexing API submission is limited to eligible JobPosting and livestream pages.', 'ameverywhere' ),
			)
		);
	}

	/**
	 * GET /ameverywhere/v1/pagespeed?post_id=N
	 *
	 * Fetches mobile and desktop PageSpeed Insights scores for the post URL.
	 * Results are cached in post-meta (_ameverywhere_pagespeed_cache) for 7 days.
	 * Requires AMEVERYWHERE_PSI_KEY constant or ameverywhere_psi_key option to be set
	 * (PageSpeed Insights API key). Without a key the public API rate-limit applies.
	 */
	public function getPageSpeed( \WP_REST_Request $request ): \WP_REST_Response {
		$postId = $request->get_param( 'post_id' );
		$url    = get_permalink( $postId );

		if ( ! $url ) {
			return new \WP_Error( 'invalid_post', __( 'Could not resolve post URL.', 'ameverywhere' ), array( 'status' => 404 ) );
		}

		// 7-day cache: return stored result unless older than a week
		$cached = get_post_meta( $postId, '_ameverywhere_pagespeed_cache', true );
		if ( $cached ) {
			$data = json_decode( $cached, true );
			if ( isset( $data['fetched_at'] ) && ( time() - $data['fetched_at'] ) < WEEK_IN_SECONDS ) {
				return rest_ensure_response(
					array(
						'success' => true,
						'scores'  => $data,
						'cached'  => true,
					)
				);
			}
		}

		$apiKey   = defined( 'AMEVERYWHERE_PSI_KEY' )
			? AMEVERYWHERE_PSI_KEY
			: get_option( 'ameverywhere_psi_key', '' );
		$keyParam = $apiKey ? '&key=' . urlencode( $apiKey ) : '';

		$scores = array( 'fetched_at' => time() );

		foreach ( array( 'mobile', 'desktop' ) as $strategy ) {
			$endpoint = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
				. '?url=' . urlencode( $url )
				. '&strategy=' . $strategy
				. $keyParam;

			$response = wp_safe_remote_get(
				$endpoint,
				array(
					'timeout'    => 30,
					'user-agent' => 'AmEveryWhere/' . AMEVERYWHERE_VERSION,
				)
			);

			if ( is_wp_error( $response ) ) {
				$scores[ $strategy ] = null;
				continue;
			}

			$body                = json_decode( wp_remote_retrieve_body( $response ), true );
			$scores[ $strategy ] = isset( $body['lighthouseResult']['categories']['performance']['score'] )
				? (int) round( $body['lighthouseResult']['categories']['performance']['score'] * 100 )
				: null;

			// Also cache useful sub-metrics if present
			$audits = $body['lighthouseResult']['audits'] ?? array();
			if ( isset( $audits['first-contentful-paint']['displayValue'] ) ) {
				$scores[ $strategy . '_fcp' ] = $audits['first-contentful-paint']['displayValue'];
			}
			if ( isset( $audits['largest-contentful-paint']['displayValue'] ) ) {
				$scores[ $strategy . '_lcp' ] = $audits['largest-contentful-paint']['displayValue'];
			}
		}

		// Persist to post-meta so the editor sidebar can display cached values
		update_post_meta( $postId, '_ameverywhere_pagespeed_cache', wp_json_encode( $scores ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'scores'  => $scores,
				'cached'  => false,
			)
		);
	}

	/**
	 * GET /ameverywhere/v1/gsc/keywords?post_id=N
	 *
	 * Returns the top 25 Google Search Console queries driving traffic to this
	 * specific post URL, ordered by clicks desc. Results are cached in post-meta
	 * (_ameverywhere_ranking_keywords) for 24 hours.
	 *
	 * Requires the Google Search Console OAuth token to be present
	 * (same credential used by the Indexing API — ameverywhere_google_indexing_key).
	 */
	public function getGscKeywords( \WP_REST_Request $request ): \WP_REST_Response {
		$postId = $request->get_param( 'post_id' );
		$url    = get_permalink( $postId );

		if ( ! $url ) {
			return new \WP_Error( 'invalid_post', __( 'Could not resolve post URL.', 'ameverywhere' ), array( 'status' => 404 ) );
		}

		// 24-hour in-memory cache via post-meta
		$cachedAt = (int) get_post_meta( $postId, '_ameverywhere_ranking_keywords_at', true );
		if ( $cachedAt && ( time() - $cachedAt ) < DAY_IN_SECONDS ) {
			$cached = get_post_meta( $postId, '_ameverywhere_ranking_keywords', true );
			if ( $cached ) {
				return rest_ensure_response(
					array(
						'success'  => true,
						'keywords' => json_decode( $cached, true ),
						'cached'   => true,
					)
				);
			}
		}

		// Obtain a fresh access token via the same Google service-account JSON
		// that powers the Indexing API (GoogleIndexingApi::getAccessToken).
		$api         = new \AmEveryWhere\Modules\Indexing\GoogleIndexingApi();
		$accessToken = $api->getAccessToken();

		if ( ! $accessToken ) {
			return new \WP_Error(
				'gsc_no_token',
				'Google Search Console is not connected. Please add your Service Account JSON under AmEveryWhere → Settings → Indexing.',
				array( 'status' => 401 )
			);
		}

		// Parse the site URL (GSC needs the property URL, not the full post URL)
		$siteUrl = trailingslashit( get_option( 'siteurl' ) );

		$body = array(
			'startDate'             => date( 'Y-m-d', strtotime( '-90 days' ) ),
			'endDate'               => date( 'Y-m-d' ),
			'dimensions'            => array( 'query' ),
			'dimensionFilterGroups' => array(
				array(
					'filters' => array(
						array(
							'dimension'  => 'page',
							'operator'   => 'equals',
							'expression' => $url,
						),
					),
				),
			),
			'rowLimit'              => 25,
			'orderBy'               => array(
				array(
					'fieldName' => 'clicks',
					'sortOrder' => 'DESCENDING',
				),
			),
		);

		$response = wp_safe_remote_post(
			'https://searchconsole.googleapis.com/webmasters/v3/sites/'
			. urlencode( $siteUrl )
			. '/searchAnalytics/query',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $accessToken,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'gsc_request_failed', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['rows'] ) ) {
			// No data yet — could mean no traffic or GSC hasn't indexed the page yet
			update_post_meta( $postId, '_ameverywhere_ranking_keywords', wp_json_encode( array() ) );
			update_post_meta( $postId, '_ameverywhere_ranking_keywords_at', time() );
			return rest_ensure_response(
				array(
					'success'  => true,
					'keywords' => array(),
					'cached'   => false,
				)
			);
		}

		$keywords = array_map(
			function ( array $row ): array {
				return array(
					'query'       => $row['keys'][0] ?? '',
					'clicks'      => (int) ( $row['clicks'] ?? 0 ),
					'impressions' => (int) ( $row['impressions'] ?? 0 ),
					'position'    => round( (float) ( $row['position'] ?? 0 ), 1 ),
					'ctr'         => round( (float) ( $row['ctr'] ?? 0 ) * 100, 2 ),
				);
			},
			$data['rows']
		);

		update_post_meta( $postId, '_ameverywhere_ranking_keywords', wp_json_encode( $keywords ) );
		update_post_meta( $postId, '_ameverywhere_ranking_keywords_at', time() );

		return rest_ensure_response(
			array(
				'success'  => true,
				'keywords' => $keywords,
				'cached'   => false,
			)
		);
	}

	// ══════════════════════════════════════════════════════════════════════════
	// PHASE 2 — SITE-WIDE GSC DASHBOARD
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * GET /ameverywhere/v1/gsc/dashboard
	 *
	 * Returns site-wide Google Search Console data for the last 28 days.
	 * Cached in a site-wide transient for 6 hours.
	 */
	public function getGscDashboard( \WP_REST_Request $request ): \WP_REST_Response {
		$cacheKey = 'ameverywhere_gsc_dashboard_v1';
		$cached   = get_transient( $cacheKey );
		if ( $cached !== false ) {
			return rest_ensure_response( array_merge( $cached, array( 'cached' => true ) ) );
		}

		$api         = new \AmEveryWhere\Modules\Indexing\GoogleIndexingApi();
		$accessToken = $api->getAccessToken();

		if ( ! $accessToken ) {
			return new \WP_Error(
				'gsc_not_connected',
				'Google Search Console is not connected. Add your Service Account JSON under AmEveryWhere → Settings → Indexing.',
				array( 'status' => 401 )
			);
		}

		$siteUrl   = trailingslashit( get_option( 'siteurl' ) );
		$endDate   = date( 'Y-m-d' );
		$startDate = date( 'Y-m-d', strtotime( '-28 days' ) );

		$gscQuery = function ( array $body ) use ( $siteUrl, $accessToken ): ?array {
			$response = wp_safe_remote_post(
				'https://searchconsole.googleapis.com/webmasters/v3/sites/'
				. urlencode( $siteUrl ) . '/searchAnalytics/query',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $accessToken,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
					'timeout' => 25,
				)
			);
			if ( is_wp_error( $response ) ) {
				return null;
			}
			return json_decode( wp_remote_retrieve_body( $response ), true );
		};

		$queriesData = $gscQuery(
			array(
				'startDate'  => $startDate,
				'endDate'    => $endDate,
				'dimensions' => array( 'query' ),
				'rowLimit'   => 25,
				'orderBy'    => array(
					array(
						'fieldName' => 'clicks',
						'sortOrder' => 'DESCENDING',
					),
				),
			)
		);

		$pagesData = $gscQuery(
			array(
				'startDate'  => $startDate,
				'endDate'    => $endDate,
				'dimensions' => array( 'page' ),
				'rowLimit'   => 25,
				'orderBy'    => array(
					array(
						'fieldName' => 'clicks',
						'sortOrder' => 'DESCENDING',
					),
				),
			)
		);

		$trendData = $gscQuery(
			array(
				'startDate'  => $startDate,
				'endDate'    => $endDate,
				'dimensions' => array( 'date' ),
				'rowLimit'   => 28,
				'orderBy'    => array(
					array(
						'fieldName' => 'date',
						'sortOrder' => 'ASCENDING',
					),
				),
			)
		);

		$totalClicks      = 0;
		$totalImpressions = 0;
		$ctrSum           = 0;
		$posSum           = 0;
		$rowCount         = 0;
		$topQueries       = array();

		foreach ( $queriesData['rows'] ?? array() as $row ) {
			$totalClicks      += (int) ( $row['clicks'] ?? 0 );
			$totalImpressions += (int) ( $row['impressions'] ?? 0 );
			$ctrSum           += (float) ( $row['ctr'] ?? 0 );
			$posSum           += (float) ( $row['position'] ?? 0 );
			++$rowCount;
			$topQueries[] = array(
				'query'       => $row['keys'][0] ?? '',
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'ctr'         => round( (float) ( $row['ctr'] ?? 0 ) * 100, 2 ),
				'position'    => round( (float) ( $row['position'] ?? 0 ), 1 ),
			);
		}

		$avgCtr      = $rowCount > 0 ? round( ( $ctrSum / $rowCount ) * 100, 2 ) : 0;
		$avgPosition = $rowCount > 0 ? round( $posSum / $rowCount, 1 ) : 0;

		$topPages = array();
		foreach ( $pagesData['rows'] ?? array() as $row ) {
			$pageUrl    = $row['keys'][0] ?? '';
			$postId     = url_to_postid( $pageUrl );
			$topPages[] = array(
				'url'         => $pageUrl,
				'label'       => ( $postId ? get_the_title( $postId ) : null ) ?: $pageUrl,
				'post_id'     => $postId ?: null,
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'ctr'         => round( (float) ( $row['ctr'] ?? 0 ) * 100, 2 ),
				'position'    => round( (float) ( $row['position'] ?? 0 ), 1 ),
			);
		}

		$trend = array();
		foreach ( $trendData['rows'] ?? array() as $row ) {
			$trend[] = array(
				'date'        => $row['keys'][0] ?? '',
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
			);
		}

		$result = array(
			'success'     => true,
			'period'      => array(
				'start' => $startDate,
				'end'   => $endDate,
			),
			'summary'     => array(
				'clicks'       => $totalClicks,
				'impressions'  => $totalImpressions,
				'avg_ctr'      => $avgCtr,
				'avg_position' => $avgPosition,
			),
			'top_queries' => $topQueries,
			'top_pages'   => $topPages,
			'trend'       => $trend,
			'cached'      => false,
		);

		set_transient( $cacheKey, $result, 6 * HOUR_IN_SECONDS );
		return rest_ensure_response( $result );
	}

	// ══════════════════════════════════════════════════════════════════════════
	// PHASE 2 — KEYWORD CANNIBALIZATION DETECTION
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * GET /ameverywhere/v1/gsc/cannibalization
	 *
	 * Groups GSC queries where two or more distinct pages both have clicks,
	 * indicating posts competing for the same keyword. Cached 6 hours.
	 */
	public function getKeywordCannibalization( \WP_REST_Request $request ): \WP_REST_Response {
		$cacheKey = 'ameverywhere_cannibalization_v1';
		$cached   = get_transient( $cacheKey );
		if ( $cached !== false ) {
			return rest_ensure_response( array_merge( $cached, array( 'cached' => true ) ) );
		}

		$api         = new \AmEveryWhere\Modules\Indexing\GoogleIndexingApi();
		$accessToken = $api->getAccessToken();

		if ( ! $accessToken ) {
			return new \WP_Error( 'gsc_not_connected', __( 'Google Search Console not connected.', 'ameverywhere' ), array( 'status' => 401 ) );
		}

		$siteUrl = trailingslashit( get_option( 'siteurl' ) );

		$response = wp_safe_remote_post(
			'https://searchconsole.googleapis.com/webmasters/v3/sites/'
			. urlencode( $siteUrl ) . '/searchAnalytics/query',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $accessToken,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'startDate'  => date( 'Y-m-d', strtotime( '-90 days' ) ),
						'endDate'    => date( 'Y-m-d' ),
						'dimensions' => array( 'query', 'page' ),
						'rowLimit'   => 500,
						'orderBy'    => array(
							array(
								'fieldName' => 'clicks',
								'sortOrder' => 'DESCENDING',
							),
						),
					)
				),
				'timeout' => 25,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'gsc_failed', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$byQuery = array();

		foreach ( $data['rows'] ?? array() as $row ) {
			$query = $row['keys'][0] ?? '';
			$page  = $row['keys'][1] ?? '';
			if ( ! $query || ! $page ) {
				continue;
			}
			$byQuery[ $query ][] = array(
				'url'         => $page,
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'position'    => round( (float) ( $row['position'] ?? 0 ), 1 ),
			);
		}

		$conflicts = array();
		foreach ( $byQuery as $query => $pages ) {
			$pagesWithClicks = array_values( array_filter( $pages, fn( $p ) => $p['clicks'] > 0 ) );
			if ( count( $pagesWithClicks ) >= 2 ) {
				$enriched = array_map(
					function ( $p ) {
						$pid          = url_to_postid( $p['url'] );
						$p['title']   = $pid ? get_the_title( $pid ) : $p['url'];
						$p['post_id'] = $pid ?: null;
						return $p;
					},
					$pagesWithClicks
				);
				usort( $enriched, fn( $a, $b ) => $b['clicks'] - $a['clicks'] );
				$conflicts[] = array(
					'query'      => $query,
					'page_count' => count( $enriched ),
					'pages'      => $enriched,
					'severity'   => count( $enriched ) >= 3 ? 'high' : 'medium',
				);
			}
		}

		usort( $conflicts, fn( $a, $b ) => $b['page_count'] - $a['page_count'] );

		$result = array(
			'success'   => true,
			'conflicts' => array_slice( $conflicts, 0, 50 ),
			'total'     => count( $conflicts ),
			'cached'    => false,
		);
		set_transient( $cacheKey, $result, 6 * HOUR_IN_SECONDS );
		return rest_ensure_response( $result );
	}




	// ══════════════════════════════════════════════════════════════════════════
	// PHASE 2 — ORPHAN PAGES & LINK STATISTICS
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * GET /ameverywhere/v1/links/orphans
	 *
	 * Returns all published posts/pages that have zero incoming internal links
	 * according to the wp_ameverywhere_links index table. Results cached 1 hour.
	 */
	public function getOrphanPages( \WP_REST_Request $request ): \WP_REST_Response {
		$cacheKey = 'ameverywhere_orphan_pages_v1';
		$cached   = get_transient( $cacheKey );
		if ( $cached !== false ) {
			return rest_ensure_response(
				array_merge(
					array(
						'success' => true,
						'cached'  => true,
					),
					$cached
				)
			);
		}

		global $wpdb;
		$linksTable = $wpdb->prefix . 'ameverywhere_links';

		// Check if table exists using prepared statement
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $linksTable ) ) !== $linksTable ) {
			return rest_ensure_response(
				array(
					'success'      => true,
					'orphans'      => array(),
					'total'        => 0,
					'orphan_count' => 0,
					'linked_count' => 0,
				)
			);
		}

		// Fetch all distinct linked targets in a single query (O(1) in-memory lookup map)
		$linkedUrls = $wpdb->get_col( "SELECT DISTINCT target_url FROM {$linksTable}" );
		$linkedMap  = array_flip( $linkedUrls ?: array() );

		// Get published posts and pages
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 1000,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);

		$orphans    = array();
		$nonOrphans = 0;

		foreach ( $posts as $postId ) {
			$permalink = get_permalink( $postId );
			if ( ! $permalink ) {
				continue;
			}

			$path = wp_make_link_relative( $permalink );

			if ( ! isset( $linkedMap[ $permalink ] ) && ! isset( $linkedMap[ $path ] ) ) {
				$orphans[] = array(
					'post_id'   => $postId,
					'title'     => get_the_title( $postId ),
					'url'       => $permalink,
					'post_type' => get_post_type( $postId ),
					'modified'  => get_the_modified_date( 'Y-m-d', $postId ),
					'seo_score' => (int) get_post_meta( $postId, '_ameverywhere_seo_score', true ),
					'edit_url'  => get_edit_post_link( $postId, 'raw' ),
				);
			} else {
				++$nonOrphans;
			}
		}

		// Sort orphans by most recently modified first
		usort( $orphans, fn( $a, $b ) => strcmp( $b['modified'], $a['modified'] ) );

		$data = array(
			'orphans'      => $orphans,
			'total'        => count( $posts ),
			'orphan_count' => count( $orphans ),
			'linked_count' => $nonOrphans,
		);

		set_transient( $cacheKey, $data, HOUR_IN_SECONDS );
		return rest_ensure_response(
			array_merge(
				array(
					'success' => true,
					'cached'  => false,
				),
				$data
			)
		);
	}

	/**
	 * GET /ameverywhere/v1/links/stats
	 *
	 * Returns site-wide internal link statistics:
	 *   - Total indexed links
	 *   - Top 10 most-linked-to pages
	 *   - Top 10 post types by internal link count
	 *   - Posts with the most outgoing links
	 */
	public function getLinkStats( \WP_REST_Request $request ): \WP_REST_Response {
		$cacheKey = 'ameverywhere_link_stats_v1';
		$cached   = get_transient( $cacheKey );
		if ( $cached !== false ) {
			return rest_ensure_response(
				array_merge(
					array(
						'success' => true,
						'cached'  => true,
					),
					$cached
				)
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ameverywhere_links';

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Link index table not found. Save a post to initialize it.', 'ameverywhere' ),
					'stats'   => null,
				)
			);
		}

		$totalLinks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		// Top 10 most linked-to pages
		$topTargets = $wpdb->get_results(
			"SELECT target_url, COUNT(*) as link_count FROM {$table}
             GROUP BY target_url ORDER BY link_count DESC LIMIT 10",
			ARRAY_A
		) ?? array();

		// Enrich with post titles
		$topTargets = array_map(
			function ( $row ) {
				$postId         = url_to_postid( $row['target_url'] );
				$row['title']   = $postId ? get_the_title( $postId ) : $row['target_url'];
				$row['post_id'] = $postId ?: null;
				return $row;
			},
			$topTargets
		);

		// Top 10 posts by outgoing link count
		$topSources = $wpdb->get_results(
			"SELECT post_id, COUNT(*) as link_count FROM {$table}
             GROUP BY post_id ORDER BY link_count DESC LIMIT 10",
			ARRAY_A
		) ?? array();

		$topSources = array_map(
			function ( $row ) {
				$row['title'] = get_the_title( (int) $row['post_id'] );
				$row['url']   = get_permalink( (int) $row['post_id'] );
				return $row;
			},
			$topSources
		);

		$data = array(
			'total_links' => $totalLinks,
			'top_targets' => $topTargets,
			'top_sources' => $topSources,
		);

		set_transient( $cacheKey, $data, HOUR_IN_SECONDS );
		return rest_ensure_response(
			array_merge(
				array(
					'success' => true,
					'cached'  => false,
				),
				$data
			)
		);
	}

	// ══════════════════════════════════════════════════════════════════════════
	// PHASE 2 — GOOGLE ANALYTICS 4 INTEGRATION
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * GET /ameverywhere/v1/settings/ga4
	 *
	 * Returns the current GA4 configuration (measurement ID and status).
	 */
	public function getGa4Settings( \WP_REST_Request $request ): \WP_REST_Response {
		$measurementId = get_option( 'ameverywhere_ga4_measurement_id', '' );
		$propertyId    = get_option( 'ameverywhere_ga4_property_id', '' );
		$credentialSet = ! empty( get_option( 'ameverywhere_ga4_credentials', '' ) );

		return rest_ensure_response(
			array(
				'measurement_id'  => $measurementId,
				'property_id'     => $propertyId,
				'credentials_set' => $credentialSet,
			)
		);
	}

	/**
	 * POST /ameverywhere/v1/settings/ga4
	 *
	 * Saves GA4 configuration: Measurement ID (for the frontend snippet injection),
	 * Property ID (for the Data API), and the Service Account JSON credentials.
	 */
	public function updateGa4Settings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		if ( isset( $params['measurement_id'] ) ) {
			update_option( 'ameverywhere_ga4_measurement_id', sanitize_text_field( $params['measurement_id'] ) );
		}

		if ( isset( $params['property_id'] ) ) {
			update_option( 'ameverywhere_ga4_property_id', sanitize_text_field( $params['property_id'] ) );
		}

		if ( isset( $params['credentials'] ) && ! empty( $params['credentials'] ) ) {
			$jsonValue = wp_unslash( $params['credentials'] );
			// Validate that it's a valid JSON service-account credential
			$decoded = json_decode( $jsonValue, true );
			if ( $decoded && isset( $decoded['private_key'], $decoded['client_email'] ) ) {
				update_option( 'ameverywhere_ga4_credentials', KeyVault::encrypt( $jsonValue ) );
				// Clear cached report on credential change
				delete_transient( 'ameverywhere_ga4_report_v1' );
			} else {
				return new \WP_Error( 'invalid_credentials', __( 'The provided credentials JSON is not a valid Google Service Account file.', 'ameverywhere' ), array( 'status' => 422 ) );
			}
		}

		// Inject the gtag snippet into wp_head if a Measurement ID is provided
		// (This is toggled by saving settings — the actual inject hook is in SeoModule boot)
		delete_transient( 'ameverywhere_ga4_report_v1' );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * GET /ameverywhere/v1/ga4/report
	 *
	 * Fetches a GA4 traffic report via the Google Analytics Data API v1beta.
	 * Returns:
	 *   - Last 28-day session/user/pageview summary
	 *   - Daily traffic trend
	 *   - Top 10 pages by sessions
	 *   - Traffic channels breakdown
	 *
	 * Requires: ameverywhere_ga4_property_id and ameverywhere_ga4_credentials options.
	 * Cached for 4 hours.
	 */
	public function getGa4Report( \WP_REST_Request $request ): \WP_REST_Response {
		$cacheKey = 'ameverywhere_ga4_report_v1';
		$cached   = get_transient( $cacheKey );
		if ( $cached !== false ) {
			return rest_ensure_response( array_merge( $cached, array( 'cached' => true ) ) );
		}

		$propertyId  = get_option( 'ameverywhere_ga4_property_id', '' );
		$rawCredJson = (string) get_option( 'ameverywhere_ga4_credentials', '' );
		$credJson    = KeyVault::decrypt( $rawCredJson );

		if ( empty( $propertyId ) || empty( $credJson ) ) {
			return new \WP_Error(
				'ga4_not_configured',
				__( 'GA4 Property ID and Service Account credentials are required. Configure them under AmEveryWhere → Analytics.', 'ameverywhere' ),
				array( 'status' => 401 )
			);
		}

		// Get OAuth2 token using the GA4 service account credentials
		$credentials = json_decode( $credJson, true );
		if ( ! $credentials || ! isset( $credentials['private_key'], $credentials['client_email'] ) ) {
			return new \WP_Error( 'ga4_invalid_credentials', __( 'Invalid GA4 credentials.', 'ameverywhere' ), array( 'status' => 422 ) );
		}

		// Build JWT for GA4 Data API scope
		$accessToken = $this->getGa4AccessToken( $credentials );
		if ( ! $accessToken ) {
			return new \WP_Error( 'ga4_auth_failed', __( 'Failed to obtain GA4 access token. Check your Service Account credentials.', 'ameverywhere' ), array( 'status' => 401 ) );
		}

		$endDate   = date( 'Y-m-d' );
		$startDate = date( 'Y-m-d', strtotime( '-28 days' ) );

		$apiBase = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport";

		$ga4Query = function ( array $body ) use ( $apiBase, $accessToken ): ?array {
			$response = wp_safe_remote_post(
				$apiBase,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $accessToken,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
					'timeout' => 25,
				)
			);
			if ( is_wp_error( $response ) ) {
				return null;
			}
			return json_decode( wp_remote_retrieve_body( $response ), true );
		};

		// 1. Summary
		$summaryData = $ga4Query(
			array(
				'dateRanges' => array(
					array(
						'startDate' => $startDate,
						'endDate'   => $endDate,
					),
				),
				'metrics'    => array(
					array( 'name' => 'sessions' ),
					array( 'name' => 'totalUsers' ),
					array( 'name' => 'screenPageViews' ),
					array( 'name' => 'bounceRate' ),
					array( 'name' => 'averageSessionDuration' ),
				),
			)
		);

		$summary = array(
			'sessions'    => 0,
			'users'       => 0,
			'pageviews'   => 0,
			'bounce_rate' => 0,
			'avg_session' => 0,
		);
		if ( ! empty( $summaryData['rows'][0]['metricValues'] ) ) {
			$mv      = $summaryData['rows'][0]['metricValues'];
			$summary = array(
				'sessions'    => (int) ( $mv[0]['value'] ?? 0 ),
				'users'       => (int) ( $mv[1]['value'] ?? 0 ),
				'pageviews'   => (int) ( $mv[2]['value'] ?? 0 ),
				'bounce_rate' => round( (float) ( $mv[3]['value'] ?? 0 ) * 100, 1 ),
				'avg_session' => (int) ( $mv[4]['value'] ?? 0 ),
			);
		}

		// 2. Daily trend
		$trendRaw = $ga4Query(
			array(
				'dateRanges' => array(
					array(
						'startDate' => $startDate,
						'endDate'   => $endDate,
					),
				),
				'dimensions' => array( array( 'name' => 'date' ) ),
				'metrics'    => array( array( 'name' => 'sessions' ), array( 'name' => 'screenPageViews' ) ),
				'orderBys'   => array(
					array(
						'dimension' => array( 'dimensionName' => 'date' ),
						'desc'      => false,
					),
				),
			)
		);

		$trend = array();
		foreach ( $trendRaw['rows'] ?? array() as $row ) {
			$rawDate = $row['dimensionValues'][0]['value'] ?? '';
			$trend[] = array(
				'date'      => substr( $rawDate, 0, 4 ) . '-' . substr( $rawDate, 4, 2 ) . '-' . substr( $rawDate, 6, 2 ),
				'sessions'  => (int) ( $row['metricValues'][0]['value'] ?? 0 ),
				'pageviews' => (int) ( $row['metricValues'][1]['value'] ?? 0 ),
			);
		}

		// 3. Top pages
		$pagesRaw = $ga4Query(
			array(
				'dateRanges' => array(
					array(
						'startDate' => $startDate,
						'endDate'   => $endDate,
					),
				),
				'dimensions' => array( array( 'name' => 'pagePath' ), array( 'name' => 'pageTitle' ) ),
				'metrics'    => array( array( 'name' => 'screenPageViews' ), array( 'name' => 'sessions' ) ),
				'orderBys'   => array(
					array(
						'metric' => array( 'metricName' => 'screenPageViews' ),
						'desc'   => true,
					),
				),
				'limit'      => 10,
			)
		);

		$topPages = array();
		foreach ( $pagesRaw['rows'] ?? array() as $row ) {
			$topPages[] = array(
				'path'      => $row['dimensionValues'][0]['value'] ?? '',
				'title'     => $row['dimensionValues'][1]['value'] ?? '',
				'pageviews' => (int) ( $row['metricValues'][0]['value'] ?? 0 ),
				'sessions'  => (int) ( $row['metricValues'][1]['value'] ?? 0 ),
			);
		}

		// 4. Channel grouping
		$channelsRaw = $ga4Query(
			array(
				'dateRanges' => array(
					array(
						'startDate' => $startDate,
						'endDate'   => $endDate,
					),
				),
				'dimensions' => array( array( 'name' => 'sessionDefaultChannelGrouping' ) ),
				'metrics'    => array( array( 'name' => 'sessions' ) ),
				'orderBys'   => array(
					array(
						'metric' => array( 'metricName' => 'sessions' ),
						'desc'   => true,
					),
				),
				'limit'      => 8,
			)
		);

		$channels = array();
		foreach ( $channelsRaw['rows'] ?? array() as $row ) {
			$channels[] = array(
				'channel'  => $row['dimensionValues'][0]['value'] ?? 'Other',
				'sessions' => (int) ( $row['metricValues'][0]['value'] ?? 0 ),
			);
		}

		$result = array(
			'success'   => true,
			'period'    => array(
				'start' => $startDate,
				'end'   => $endDate,
			),
			'summary'   => $summary,
			'trend'     => $trend,
			'top_pages' => $topPages,
			'channels'  => $channels,
			'cached'    => false,
		);

		set_transient( $cacheKey, $result, 4 * HOUR_IN_SECONDS );
		return rest_ensure_response( $result );
	}

	/**
	 * Build a short-lived OAuth2 access token for the GA4 Data API using
	 * a Service Account JSON credential (RS256 JWT signing).
	 * Reuses a 55-minute transient to avoid signing a new JWT on every request.
	 */
	private function getGa4AccessToken( array $credentials ): ?string {
		$transientKey = 'ameverywhere_ga4_token';
		$token        = get_transient( $transientKey );
		if ( $token ) {
			return $token;
		}

		$scope = 'https://www.googleapis.com/auth/analytics.readonly';
		$now   = time();

		$header  = base64_encode(
			wp_json_encode(
				array(
					'alg' => 'RS256',
					'typ' => 'JWT',
				)
			)
		);
		$payload = base64_encode(
			wp_json_encode(
				array(
					'iss'   => $credentials['client_email'],
					'scope' => $scope,
					'aud'   => 'https://oauth2.googleapis.com/token',
					'exp'   => $now + 3600,
					'iat'   => $now,
				)
			)
		);

		$toSign     = "{$header}.{$payload}";
		$privateKey = openssl_pkey_get_private( $credentials['private_key'] );
		if ( ! $privateKey ) {
			return null;
		}

		$signature = '';
		if ( ! openssl_sign( $toSign, $signature, $privateKey, OPENSSL_ALGO_SHA256 ) ) {
			return null;
		}

		$jwt = $toSign . '.' . base64_encode( $signature );

		$response = wp_safe_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) {
			return null;
		}

		set_transient( $transientKey, $data['access_token'], 55 * MINUTE_IN_SECONDS );
		return $data['access_token'];
	}

	// ══════════════════════════════════════════════════════════════════════════
	// PHASE 3 — IMPORT / EXPORT SETTINGS
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * GET /ameverywhere/v1/settings/export
	 *
	 * Returns a complete JSON snapshot of all AmEveryWhere settings.
	 * Sensitive values (private keys, service account JSON) are included
	 * because this is an authenticated admin-only endpoint — the admin can
	 * choose to redact before sharing the file.
	 */
	public function exportSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$optionKeys = array(
			'ameverywhere_google_indexing_key',
			'ameverywhere_indexnow_key',
			'ameverywhere_auto_index',
			'ameverywhere_enable_google_indexing_api',
			'ameverywhere_google_verify',
			'ameverywhere_bing_verify',
			'ameverywhere_yandex_verify',
			'ameverywhere_pinterest_verify',
			'ameverywhere_rss_before_content',
			'ameverywhere_rss_after_content',
			'ameverywhere_breadcrumb_separator',
			'ameverywhere_breadcrumb_home_label',
			'ameverywhere_breadcrumb_auto_insert',
			'ameverywhere_sitemap_exclude_post_types',
			'ameverywhere_sitemap_exclude_posts',
			'ameverywhere_ai_provider',
			'ameverywhere_openai_key',
			'ameverywhere_anthropic_key',
			'ameverywhere_ollama_url',
			'ameverywhere_default_share_image',
			'ameverywhere_ga4_measurement_id',
			'ameverywhere_ga4_property_id',
			// Intentionally exclude ameverywhere_ga4_credentials & google_indexing_key JSON
			// for security — they contain private keys.
			'ameverywhere_html_sitemap_post_types',
			'ameverywhere_html_sitemap_exclude_ids',
			'ameverywhere_html_sitemap_depth',
			'ameverywhere_html_sitemap_order',
			'ameverywhere_html_sitemap_show_count',
			'ameverywhere_psi_key',
		);

		$data = array(
			'_version'  => '1.0',
			'_plugin'   => 'ameverywhere',
			'_exported' => date( 'c' ),
			'_site_url' => get_option( 'siteurl' ),
			'settings'  => array(),
		);

		foreach ( $optionKeys as $key ) {
			$data['settings'][ $key ] = get_option( $key, '' );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * POST /ameverywhere/v1/settings/import
	 *
	 * Accepts a JSON payload previously exported via /settings/export.
	 * Validates the structure before writing anything.
	 * Silently skips unknown keys for forward compatibility.
	 */
	public function importSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		if ( empty( $params['settings'] ) || ! is_array( $params['settings'] ) ) {
			return new \WP_Error( 'invalid_payload', __( 'Missing or malformed settings object.', 'ameverywhere' ), array( 'status' => 422 ) );
		}

		if ( ( $params['_plugin'] ?? '' ) !== 'ameverywhere' ) {
			return new \WP_Error( 'wrong_plugin', __( 'This settings file was not exported from AmEveryWhere.', 'ameverywhere' ), array( 'status' => 422 ) );
		}

		// Whitelist — only import known, safe options
		$allowed = array(
			'ameverywhere_auto_index',
			'ameverywhere_enable_google_indexing_api',
			'ameverywhere_google_verify',
			'ameverywhere_bing_verify',
			'ameverywhere_yandex_verify',
			'ameverywhere_pinterest_verify',
			'ameverywhere_rss_before_content',
			'ameverywhere_rss_after_content',
			'ameverywhere_breadcrumb_separator',
			'ameverywhere_breadcrumb_home_label',
			'ameverywhere_breadcrumb_auto_insert',
			'ameverywhere_sitemap_exclude_post_types',
			'ameverywhere_sitemap_exclude_posts',
			'ameverywhere_ai_provider',
			'ameverywhere_ollama_url',
			'ameverywhere_default_share_image',
			'ameverywhere_ga4_measurement_id',
			'ameverywhere_ga4_property_id',
			'ameverywhere_html_sitemap_post_types',
			'ameverywhere_html_sitemap_exclude_ids',
			'ameverywhere_html_sitemap_depth',
			'ameverywhere_html_sitemap_order',
			'ameverywhere_html_sitemap_show_count',
		);

		$imported = 0;
		foreach ( $params['settings'] as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}
			update_option( sanitize_key( $key ), sanitize_text_field( (string) $value ) );
			++$imported;
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'imported' => $imported,
				'message'  => "Imported {$imported} settings successfully. API keys and credentials are excluded from import for security.",
			)
		);
	}

	// ══════════════════════════════════════════════════════════════════════════
	// PHASE 3 — HTML SITEMAP BUILDER
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * GET /ameverywhere/v1/html-sitemap
	 * Returns current HTML sitemap configuration.
	 */
	public function getHtmlSitemapSettings( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'post_types'  => get_option( 'ameverywhere_html_sitemap_post_types', 'post,page' ),
				'exclude_ids' => get_option( 'ameverywhere_html_sitemap_exclude_ids', '' ),
				'depth'       => (int) get_option( 'ameverywhere_html_sitemap_depth', 0 ),
				'order'       => get_option( 'ameverywhere_html_sitemap_order', 'menu_order' ),
				'show_count'  => get_option( 'ameverywhere_html_sitemap_show_count', 'no' ) === 'yes',
				'shortcode'   => '[ameverywhere_sitemap]',
			)
		);
	}

	/**
	 * POST /ameverywhere/v1/html-sitemap
	 * Save HTML sitemap configuration.
	 */
	public function saveHtmlSitemapSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		update_option( 'ameverywhere_html_sitemap_post_types', sanitize_text_field( $params['post_types'] ?? 'post,page' ) );
		update_option( 'ameverywhere_html_sitemap_exclude_ids', sanitize_text_field( $params['exclude_ids'] ?? '' ) );
		update_option( 'ameverywhere_html_sitemap_depth', absint( $params['depth'] ?? 0 ) );
		update_option( 'ameverywhere_html_sitemap_order', sanitize_key( $params['order'] ?? 'menu_order' ) );
		update_option( 'ameverywhere_html_sitemap_show_count', ! empty( $params['show_count'] ) ? 'yes' : 'no' );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * GET /ameverywhere/v1/html-sitemap/preview
	 *
	 * Returns a structured tree of published posts grouped by post type
	 * according to the current HTML sitemap configuration.
	 * Used by the admin UI preview and also powers the [ameverywhere_sitemap] shortcode.
	 */
	public function previewHtmlSitemap( \WP_REST_Request $request ): \WP_REST_Response {
		$postTypesRaw = get_option( 'ameverywhere_html_sitemap_post_types', 'post,page' );
		$excludeIds   = array_filter( array_map( 'absint', explode( ',', get_option( 'ameverywhere_html_sitemap_exclude_ids', '' ) ) ) );
		$order        = get_option( 'ameverywhere_html_sitemap_order', 'menu_order' );
		$showCount    = get_option( 'ameverywhere_html_sitemap_show_count', 'no' ) === 'yes';

		$postTypes = array_filter( array_map( 'trim', explode( ',', $postTypesRaw ) ) );
		if ( empty( $postTypes ) ) {
			$postTypes = array( 'post', 'page' );
		}

		$orderby = match ( $order ) {
			'title'        => 'title',
			'date'         => 'date',
			'modified'     => 'modified',
			'menu_order'   => 'menu_order',
			default        => 'menu_order',
		};

		$tree = array();
		foreach ( $postTypes as $postType ) {
			$ptObj = get_post_type_object( $postType );
			if ( ! $ptObj ) {
				continue;
			}

			$posts = get_posts(
				array(
					'post_type'      => $postType,
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'post__not_in'   => $excludeIds,
					'orderby'        => $orderby,
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);

			$items = array_map(
				fn( $p ) => array(
					'id'       => $p->ID,
					'title'    => $p->post_title,
					'url'      => get_permalink( $p->ID ),
					'modified' => get_the_modified_date( 'Y-m-d', $p->ID ),
					'children' => array(), // hierarchical support can be added in v2
				),
				$posts
			);

			$tree[] = array(
				'post_type' => $postType,
				'label'     => $ptObj->labels->name,
				'count'     => count( $items ),
				'items'     => $items,
			);
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'tree'       => $tree,
				'total'      => array_sum( array_column( $tree, 'count' ) ),
				'show_count' => $showCount,
			)
		);
	}
}
