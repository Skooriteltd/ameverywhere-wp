<?php

namespace AmEveryWhere\Modules\Compliance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CcpaPrivacyTools
 *
 * CCPA/GDPR compliance toolkit:
 * - Data inventory endpoint
 * - User data export & deletion
 * - Configurable retention policy with auto-purge cron
 * - Integrates with WordPress built-in privacy exporters/erasers
 * - Honours GPC (Global Privacy Control) header
 *
 * BL-008
 */
class CcpaPrivacyTools {

	private const RETENTION_OPTION  = 'ameverywhere_privacy_retention';
	private const HONOUR_GPC_OPTION = 'ameverywhere_honour_gpc';
	private const CRON_HOOK         = 'ameverywhere_apply_retention';

	public function boot(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
		add_action( self::CRON_HOOK, array( $this, 'applyRetentionPolicy' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'registerExporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'registerEraser' ) );
		add_action( 'admin_init', array( $this, 'addPrivacyPolicyText' ) );
		add_action( 'init', array( $this, 'handleGpcSignal' ) );

		// Schedule daily retention purge if not already scheduled
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	// ── GPC signal ────────────────────────────────────────────────────────────

	public function handleGpcSignal(): void {
		if ( get_option( self::HONOUR_GPC_OPTION, 'yes' ) !== 'yes' ) {
			return;
		}

		$gpc = $_SERVER['HTTP_SEC_GPC'] ?? '';
		if ( $gpc === '1' ) {
			// Signal to other modules that AI/tracking features should be suppressed
			do_action( 'ameverywhere_gpc_opt_out' );
			setcookie(
				'ameverywhere_gpc',
				'1',
				array(
					'expires'  => time() + YEAR_IN_SECONDS,
					'path'     => '/',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
	}

	// ── REST routes ───────────────────────────────────────────────────────────

	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'ameverywhere/v1',
			'/privacy/data-inventory',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getDataInventory' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/privacy/export',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'exportUserData' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/privacy/delete',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'deleteUserData' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/privacy/retention-settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getRetentionSettings' ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveRetentionSettings' ),
					'permission_callback' => $adminCap,
				),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/privacy/apply-retention',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'triggerRetention' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/privacy/gpc-settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn() => rest_ensure_response( array( 'honour_gpc' => get_option( self::HONOUR_GPC_OPTION, 'yes' ) === 'yes' ) ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => function ( \WP_REST_Request $req ) {
						$p = $req->get_json_params();
						update_option( self::HONOUR_GPC_OPTION, ! empty( $p['honour_gpc'] ) ? 'yes' : 'no' );
						return rest_ensure_response( array( 'success' => true ) );
					},
					'permission_callback' => $adminCap,
				),
			)
		);
	}

	// ── REST callbacks ────────────────────────────────────────────────────────

	public function getDataInventory( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'data_types' => array(
					array(
						'store'       => 'ameverywhere_404_logs (DB table)',
						'data_type'   => 'URL request logs, referrer URLs, User-Agent strings',
						'sensitivity' => 'low',
						'retention'   => 'Configurable (default: 90 days)',
					),
					array(
						'store'       => 'ameverywhere_redirects (DB table)',
						'data_type'   => 'Redirect rules (URLs only, no PII)',
						'sensitivity' => 'none',
						'retention'   => 'Indefinite',
					),
					array(
						'store'       => 'ameverywhere_ai_usage (DB table)',
						'data_type'   => 'AI token usage per WordPress user ID',
						'sensitivity' => 'low',
						'retention'   => 'Configurable (default: 365 days)',
					),
					array(
						'store'       => 'wp_options (ameverywhere_* keys)',
						'data_type'   => 'Plugin settings, API keys (encrypted)',
						'sensitivity' => 'medium',
						'retention'   => 'Until plugin uninstalled',
					),
					array(
						'store'       => 'wp_postmeta (_ameverywhere_* keys)',
						'data_type'   => 'Per-post SEO metadata (titles, descriptions, schema overrides)',
						'sensitivity' => 'none',
						'retention'   => 'Until post deleted',
					),
				),
			)
		);
	}

	public function exportUserData( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = $request->get_json_params();
		$email  = sanitize_email( $params['email'] ?? '' );

		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', 'A valid email address is required.', array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->getUserData( $email ),
			)
		);
	}

	public function deleteUserData( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = $request->get_json_params();
		$email  = sanitize_email( $params['email'] ?? '' );

		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', 'A valid email address is required.', array( 'status' => 400 ) );
		}

		$deleted = $this->eraseUserData( $email );

		return rest_ensure_response(
			array(
				'success'         => true,
				'records_deleted' => $deleted,
			)
		);
	}

	public function getRetentionSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$defaults = array(
			'keep_404_logs_days'   => 90,
			'keep_usage_logs_days' => 365,
		);
		return rest_ensure_response( array_merge( $defaults, get_option( self::RETENTION_OPTION, array() ) ) );
	}

	public function saveRetentionSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$config = array(
			'keep_404_logs_days'   => max( 1, (int) ( $params['keep_404_logs_days'] ?? 90 ) ),
			'keep_usage_logs_days' => max( 1, (int) ( $params['keep_usage_logs_days'] ?? 365 ) ),
		);
		update_option( self::RETENTION_OPTION, $config );
		return rest_ensure_response(
			array(
				'success'  => true,
				'settings' => $config,
			)
		);
	}

	public function triggerRetention( \WP_REST_Request $request ): \WP_REST_Response {
		wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Retention purge scheduled.',
			)
		);
	}

	// ── Cron: retention purge ─────────────────────────────────────────────────

	public function applyRetentionPolicy(): void {
		global $wpdb;

		$config = array_merge(
			array(
				'keep_404_logs_days'   => 90,
				'keep_usage_logs_days' => 365,
			),
			get_option( self::RETENTION_OPTION, array() )
		);

		// Purge old 404 logs
		$logsTable   = $wpdb->prefix . 'ameverywhere_404_logs';
		$tableExists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $logsTable ) ) );
		if ( $tableExists ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $logsTable WHERE last_hit < DATE_SUB(NOW(), INTERVAL %d DAY)",
					$config['keep_404_logs_days']
				)
			);
		}

		// Purge old AI usage logs
		$usageTable  = $wpdb->prefix . 'ameverywhere_ai_usage';
		$tableExists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $usageTable ) ) );
		if ( $tableExists ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $usageTable WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
					$config['keep_usage_logs_days']
				)
			);
		}
	}

	// ── WordPress privacy framework integration ───────────────────────────────

	public function addPrivacyPolicyText(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li><li>%s</li></ul>',
			__( 'When using the AmEveryWhere plugin, we process and store certain data to provide SEO optimizations and analytics. We recommend disclosing the following to your users:', 'ameverywhere' ),
			__( '<strong>AI Features:</strong> If AI-assisted content generation is used, excerpts of post content or meta data may be transmitted to integrated third-party AI providers (such as OpenAI or Anthropic).', 'ameverywhere' ),
			__( '<strong>External Services:</strong> The plugin may communicate with external SEO indexing APIs (e.g., Bing IndexNow) which involves transmitting published URLs.', 'ameverywhere' ),
			__( '<strong>Site Analytics & 404 Logs:</strong> To identify broken links and optimize traffic, the plugin logs missing pages (404s). By default, these logs are retained for 90 days before being automatically purged.', 'ameverywhere' ),
			__( '<strong>Administrator Usage:</strong> Feature usage tokens and interaction logs are recorded for administrative users and retained for 365 days by default.', 'ameverywhere' )
		);

		wp_add_privacy_policy_content( __( 'AmEveryWhere SEO', 'ameverywhere' ), $content );
	}

	public function registerExporter( array $exporters ): array {
		$exporters['ameverywhere'] = array(
			'exporter_friendly_name' => 'AmEveryWhere SEO Data',
			'callback'               => array( $this, 'wpPrivacyExporter' ),
		);
		return $exporters;
	}

	public function registerEraser( array $erasers ): array {
		$erasers['ameverywhere'] = array(
			'eraser_friendly_name' => 'AmEveryWhere SEO Data',
			'callback'             => array( $this, 'wpPrivacyEraser' ),
		);
		return $erasers;
	}

	public function wpPrivacyExporter( string $email, int $page = 1 ): array {
		$items = array();
		$data  = $this->getUserData( $email, $page );

		foreach ( $data['records']['ai_usage'] as $row ) {
			$items[] = array(
				'group_id'    => 'ameverywhere_ai_usage',
				'group_label' => 'AmEveryWhere AI Usage',
				'item_id'     => 'ai-usage-' . $row->id,
				'data'        => array(
					array(
						'name'  => 'Provider',
						'value' => $row->provider,
					),
					array(
						'name'  => 'Feature',
						'value' => $row->feature,
					),
					array(
						'name'  => 'Tokens',
						'value' => $row->tokens_used,
					),
					array(
						'name'  => 'Date',
						'value' => $row->created_at,
					),
				),
			);
		}

		if ( $page === 1 ) {
			foreach ( $data['records']['user_meta'] as $key => $values ) {
				$items[] = array(
					'group_id'    => 'ameverywhere_user_meta',
					'group_label' => 'AmEveryWhere User Metadata',
					'item_id'     => 'user-meta-' . $key,
					'data'        => array(
						array(
							'name'  => $key,
							'value' => implode( ', ', array_map( 'strval', (array) $values ) ),
						),
					),
				);
			}
		}

		return array(
			'data' => $items,
			'done' => ! $data['has_more'],
		);
	}

	public function wpPrivacyEraser( string $email, int $page = 1 ): array {
		$deleted = $this->eraseUserData( $email );
		return array(
			'items_removed'  => $deleted > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Read the plugin data tied to one email address. This private operation is
	 * shared by REST and WordPress privacy callbacks so their behaviour cannot
	 * diverge.
	 *
	 * @return array{email:string,user_id:int|null,records:array{ai_usage:array,user_meta:array},has_more:bool}
	 */
	private function getUserData( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		$data = array(
			'email'    => $email,
			'user_id'  => $user ? (int) $user->ID : null,
			'records'  => array(
				'ai_usage'  => array(),
				'user_meta' => array(),
			),
			'has_more' => false,
		);

		if ( ! $user ) {
			return $data;
		}

		global $wpdb;
		$usageTable  = $wpdb->prefix . 'ameverywhere_ai_usage';
		$tableExists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $usageTable ) ) );
		$limit       = 100;
		$offset      = max( 0, $page - 1 ) * $limit;

		if ( $tableExists ) {
			$rows                        = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM $usageTable WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $user->ID, $limit + 1, $offset )
			);
			$data['has_more']            = count( $rows ) > $limit;
			$data['records']['ai_usage'] = array_slice( $rows, 0, $limit );
		}

		if ( $page === 1 ) {
			$allMeta                      = get_user_meta( $user->ID );
			$data['records']['user_meta'] = array_filter(
				$allMeta,
				static fn( $key ) => str_starts_with( (string) $key, 'ameverywhere' ),
				ARRAY_FILTER_USE_KEY
			);
		}

		return $data;
	}

	/**
	 * Erase data for one verified WordPress user and return the exact number of
	 * records removed. It deliberately does not report success for an empty or
	 * unmatched address.
	 */
	private function eraseUserData( string $email ): int {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return 0;
		}

		global $wpdb;
		$deleted     = 0;
		$usageTable  = $wpdb->prefix . 'ameverywhere_ai_usage';
		$tableExists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $usageTable ) ) );
		if ( $tableExists ) {
			$deleted += (int) $wpdb->delete( $usageTable, array( 'user_id' => $user->ID ), array( '%d' ) );
		}

		foreach ( array_keys( get_user_meta( $user->ID ) ) as $key ) {
			if ( str_starts_with( (string) $key, 'ameverywhere' ) && delete_user_meta( $user->ID, $key ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}
}
