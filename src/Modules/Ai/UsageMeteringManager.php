<?php

namespace AmEveryWhere\Modules\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UsageMeteringManager
 *
 * Tracks AI token usage per user, per provider, and per feature.
 * Enforces configurable soft/hard caps and provides usage dashboards.
 *
 * BL-011
 */
class UsageMeteringManager {

	private const TABLE_NAME   = 'ameverywhere_ai_usage';
	private const LIMIT_OPTION = 'ameverywhere_ai_token_limits';
	private const CRON_HOOK    = 'ameverywhere_weekly_usage_email';

	private static ?self $instance = null;

	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// ── Installation ──────────────────────────────────────────────────────────

	public static function createTable(): void {
		global $wpdb;

		$table   = $wpdb->prefix . self::TABLE_NAME;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
            provider     VARCHAR(64)     NOT NULL DEFAULT '',
            feature      VARCHAR(128)    NOT NULL DEFAULT '',
            tokens_used  INT UNSIGNED    NOT NULL DEFAULT 0,
            created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	// ── Boot ──────────────────────────────────────────────────────────────────

	public function boot(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
		add_action( self::CRON_HOOK, array( $this, 'sendWeeklySummary' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::CRON_HOOK );
		}
	}

	// ── Core API ──────────────────────────────────────────────────────────────

	/**
	 * Record a token usage event.
	 */
	public function record( int $userId, string $provider, string $feature, int $tokensUsed ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . self::TABLE_NAME,
			array(
				'user_id'     => $userId,
				'provider'    => sanitize_text_field( $provider ),
				'feature'     => sanitize_text_field( $feature ),
				'tokens_used' => max( 0, $tokensUsed ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Get usage totals for a specific user for a period.
	 *
	 * @param string $period 'month'|'week'|'day'|'all'
	 */
	public function getUsage( int $userId, string $period = 'month' ): array {
		global $wpdb;
		$table      = $wpdb->prefix . self::TABLE_NAME;
		$dateClause = $this->buildDateClause( $period );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT provider, feature, SUM(tokens_used) AS total
                 FROM $table
                 WHERE user_id = %d $dateClause
                 GROUP BY provider, feature
                 ORDER BY total DESC",
				$userId
			)
		);

		$total = 0;
		foreach ( $rows as $row ) {
			$total += (int) $row->total;
		}

		return array(
			'user_id'    => $userId,
			'period'     => $period,
			'total'      => $total,
			'by_feature' => $rows,
			'limit'      => $this->getLimit(),
			'percent'    => $this->getLimit() > 0 ? round( ( $total / $this->getLimit() ) * 100, 1 ) : null,
		);
	}

	/**
	 * Get site-wide usage for a period.
	 */
	public function getSiteUsage( string $period = 'month' ): array {
		global $wpdb;
		$table      = $wpdb->prefix . self::TABLE_NAME;
		$dateClause = $this->buildDateClause( $period );

		$rows = $wpdb->get_results(
			"SELECT provider, feature, SUM(tokens_used) AS total
             FROM $table
             $dateClause
             GROUP BY provider, feature
             ORDER BY total DESC"
		);

		$total = array_sum( array_column( (array) $rows, 'total' ) );

		return array(
			'period'     => $period,
			'total'      => (int) $total,
			'by_feature' => $rows,
		);
	}

	/**
	 * Check whether a user is within their token limit.
	 */
	public function checkLimit( int $userId, string $provider = '' ): bool {
		$limit = $this->getLimit();
		if ( $limit <= 0 ) {
			return true; // No limit configured
		}

		$usage = $this->getUsage( $userId, 'month' );
		return $usage['total'] < $limit;
	}

	// ── REST routes ───────────────────────────────────────────────────────────

	public function registerRoutes(): void {
		register_rest_route(
			'ameverywhere/v1',
			'/ai/usage',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getMyUsage' ),
				'permission_callback' => fn() => is_user_logged_in(),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/ai/usage/site',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getSiteUsageEndpoint' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/ai/usage/limits',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn() => rest_ensure_response( get_option( self::LIMIT_OPTION, $this->defaultLimits() ) ),
					'permission_callback' => fn() => current_user_can( 'manage_options' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveLimits' ),
					'permission_callback' => fn() => current_user_can( 'manage_options' ),
				),
			)
		);
	}

	public function getMyUsage( \WP_REST_Request $request ): \WP_REST_Response {
		$userId = get_current_user_id();
		$period = sanitize_text_field( $request->get_param( 'period' ) ?? 'month' );
		return rest_ensure_response( $this->getUsage( $userId, $period ) );
	}

	public function getSiteUsageEndpoint( \WP_REST_Request $request ): \WP_REST_Response {
		$period = sanitize_text_field( $request->get_param( 'period' ) ?? 'month' );
		return rest_ensure_response( $this->getSiteUsage( $period ) );
	}

	public function saveLimits( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$limits = $this->defaultLimits();

		foreach ( array( 'free', 'pro', 'enterprise' ) as $tier ) {
			if ( isset( $params[ $tier ] ) ) {
				$limits[ $tier ] = max( 0, (int) $params[ $tier ] );
			}
		}

		update_option( self::LIMIT_OPTION, $limits );
		return rest_ensure_response(
			array(
				'success' => true,
				'limits'  => $limits,
			)
		);
	}

	// ── Weekly email ──────────────────────────────────────────────────────────

	public function sendWeeklySummary(): void {
		$usage   = $this->getSiteUsage( 'week' );
		$admin   = get_option( 'admin_email' );
		$subject = sprintf( '[AmEveryWhere] Weekly AI Usage Summary — %s tokens used', number_format( $usage['total'] ) );

		$body  = '<h2>AmEveryWhere Weekly AI Usage</h2>';
		$body .= '<p>Total tokens used this week: <strong>' . number_format( $usage['total'] ) . '</strong></p>';
		$body .= "<table border='1' cellpadding='5'><tr><th>Provider</th><th>Feature</th><th>Tokens</th></tr>";

		foreach ( (array) $usage['by_feature'] as $row ) {
			$body .= '<tr><td>' . esc_html( $row->provider ) . '</td><td>' . esc_html( $row->feature ) . '</td><td>' . number_format( (int) $row->total ) . '</td></tr>';
		}

		$body .= '</table>';

		wp_mail( $admin, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function getLimit(): int {
		$tier   = get_option( 'ameverywhere_user_tier', 'free' );
		$limits = get_option( self::LIMIT_OPTION, $this->defaultLimits() );
		return (int) ( $limits[ $tier ] ?? 50000 );
	}

	private function defaultLimits(): array {
		return array(
			'free'       => 50000,
			'pro'        => 500000,
			'enterprise' => 5000000,
		);
	}

	private function buildDateClause( string $period ): string {
		return match ( $period ) {
			'day'   => 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
			'week'  => 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
			'month' => 'AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
			default => '',
		};
	}
}
