<?php

namespace AmEveryWhere\Modules\TechnicalSeo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a virtual robots.txt editor with optional automatic AI crawler blocking.
 * Stores custom rules in wp_options and intercepts WordPress's default robots.txt output.
 *
 * BL-010: Editable custom AI bot list (merged with built-in AI_BOTS at runtime).
 * BL-032: LLM training data opt-out (NoAI directive + TDM-Reservation HTTP header).
 */
class RobotsTxtEditor {

	private const OPTION_KEY           = 'ameverywhere_robots_txt';
	private const CUSTOM_BOTS_OPTION   = 'ameverywhere_custom_ai_bots';
	private const TRAINING_OPT_OUT_OPT = 'ameverywhere_ai_training_optout';

	// List of major AI scraper/crawler User-Agents (built-in, always applied)
	private const AI_BOTS = array(
		'GPTBot',
		'ChatGPT-User',
		'CCBot',
		'Google-Extended',
		'Anthropic-AI',
		'Claude-Web',
		'ClaudeBot',
		'cohere-ai',
		'Omgilibot',
		'Omgili',
		'PerplexityBot',
		'YouBot',
	);

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'robots_txt', array( $this, 'filterRobotsTxt' ), 999, 2 );
		add_action( 'init', array( $this, 'proactiveBlockAiBots' ) );
		add_action( 'send_headers', array( $this, 'addTrainingOptOutHeaders' ) );
	}

	/**
	 * Register REST routes for the robots.txt editor.
	 */
	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'ameverywhere/v1',
			'/robots-txt',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getRobotsTxt' ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'updateRobotsTxt' ),
					'permission_callback' => $adminCap,
				),
			)
		);

		// BL-010: Editable AI bot list
		register_rest_route(
			'ameverywhere/v1',
			'/ai-bots',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getAiBots' ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'updateAiBots' ),
					'permission_callback' => $adminCap,
				),
			)
		);

		// BL-032: Training data opt-out toggle
		register_rest_route(
			'ameverywhere/v1',
			'/ai-training-optout',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn() => rest_ensure_response( array( 'enabled' => get_option( self::TRAINING_OPT_OUT_OPT, 'no' ) === 'yes' ) ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => function ( \WP_REST_Request $req ) {
						$p = $req->get_json_params();
						update_option( self::TRAINING_OPT_OUT_OPT, ! empty( $p['enabled'] ) ? 'yes' : 'no' );
						return rest_ensure_response( array( 'success' => true ) );
					},
					'permission_callback' => $adminCap,
				),
			)
		);
	}

	// ── BL-010: Bot list management ───────────────────────────────────────────

	/**
	 * Merge the built-in AI_BOTS with any custom additions from wp_options.
	 */
	public function getMergedBotList(): array {
		$custom = (array) get_option( self::CUSTOM_BOTS_OPTION, array() );
		return array_unique( array_merge( self::AI_BOTS, $custom ) );
	}

	public function getAiBots( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'builtin' => self::AI_BOTS,
				'custom'  => (array) get_option( self::CUSTOM_BOTS_OPTION, array() ),
				'merged'  => $this->getMergedBotList(),
			)
		);
	}

	public function updateAiBots( \WP_REST_Request $request ): \WP_REST_Response {
		$params     = $request->get_json_params();
		$customBots = array_map( 'sanitize_text_field', (array) ( $params['custom_bots'] ?? array() ) );
		// Sanitise: alphanumeric, hyphens, underscores, dots only; max 100 chars each; max 50 bots
		$customBots = array_slice(
			array_filter( $customBots, fn( $b ) => preg_match( '/^[a-zA-Z0-9\-_\.]+$/', $b ) && mb_strlen( $b ) <= 100 ),
			0,
			50
		);

		update_option( self::CUSTOM_BOTS_OPTION, array_values( $customBots ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'merged'  => $this->getMergedBotList(),
			)
		);
	}

	// ── robots.txt endpoints ──────────────────────────────────────────────────

	public function getRobotsTxt( \WP_REST_Request $request ): \WP_REST_Response {
		$custom = get_option( self::OPTION_KEY, '' );

		if ( empty( $custom ) ) {
			$custom = $this->getDefaultRobotsTxt();
		}

		return rest_ensure_response(
			array(
				'content'         => $custom,
				'block_ai_bots'   => get_option( 'ameverywhere_block_ai_bots', 'no' ) === 'yes',
				'training_optout' => get_option( self::TRAINING_OPT_OUT_OPT, 'no' ) === 'yes',
			)
		);
	}

	public function updateRobotsTxt( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		if ( isset( $params['content'] ) ) {
			update_option( self::OPTION_KEY, sanitize_textarea_field( $params['content'] ) );
		}

		if ( isset( $params['block_ai_bots'] ) ) {
			update_option( 'ameverywhere_block_ai_bots', $params['block_ai_bots'] ? 'yes' : 'no' );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	// ── robots.txt filter ─────────────────────────────────────────────────────

	public function filterRobotsTxt( string $output, bool $public ): string {
		$custom = get_option( self::OPTION_KEY, '' );

		if ( empty( $custom ) ) {
			$custom = $this->getDefaultRobotsTxt();
		}

		// BL-010: use merged bot list (built-in + custom)
		if ( get_option( 'ameverywhere_block_ai_bots', 'no' ) === 'yes' ) {
			$custom .= "\n# Block AI Crawlers and LLM Bots (AmEveryWhere AI Crawler Manager)\n";
			foreach ( $this->getMergedBotList() as $bot ) {
				$custom .= 'User-agent: ' . $bot . "\nDisallow: /\n";
			}
			$custom .= "\n";
		}

		// BL-032: LLM training data opt-out directive
		if ( get_option( self::TRAINING_OPT_OUT_OPT, 'no' ) === 'yes' ) {
			$custom .= "\n# AI Training Data Opt-Out (AmEveryWhere)\nUser-agent: *\nNoAI: 1\nNoImageAI: 1\n";
		}

		return $custom;
	}

	// ── BL-032: HTTP headers for training opt-out ─────────────────────────────

	public function addTrainingOptOutHeaders(): void {
		if ( get_option( self::TRAINING_OPT_OUT_OPT, 'no' ) !== 'yes' ) {
			return;
		}

		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		header( 'TDM-Reservation: 1' );
		header( 'X-Robots-Tag: noai, noimageai', false );
	}

	/**
	 * Returns a static method that MetaTagsGenerator can call to add a per-post AI meta tag.
	 * Usage in MetaTagsGenerator::getMetaTags(): echo RobotsTxtEditor::getPerPostAiMeta(get_the_ID());
	 */
	public static function getPerPostAiMeta( int $postId ): string {
		$allowAi = get_post_meta( $postId, '_ameverywhere_ai_training_allow', true );
		if ( $allowAi === 'yes' ) {
			return '<meta name="robots" content="ai">' . "\n";
		}

		return '';
	}

	// ── Proactive HTTP-level AI bot blocking ──────────────────────────────────

	public function proactiveBlockAiBots(): void {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( get_option( 'ameverywhere_block_ai_bots', 'no' ) !== 'yes' ) {
			return;
		}

		$userAgent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( empty( $userAgent ) ) {
			return;
		}

		foreach ( $this->getMergedBotList() as $bot ) {
			if ( stripos( $userAgent, $bot ) !== false ) {
				status_header( 403 );
				header( 'Content-Type: text/html; charset=utf-8' );
				$siteName = esc_html( get_bloginfo( 'name' ) );
				$homeUrl  = esc_url( home_url( '/' ) );
				echo "<!DOCTYPE html>
<html lang=\"en\">
<head>
<meta charset=\"UTF-8\">
<meta name=\"robots\" content=\"noindex\">
<title>403 Forbidden &mdash; {$siteName}</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f8fafc;color:#1e293b;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.wrap{max-width:480px;text-align:center;padding:40px 24px}
h1{font-size:4rem;font-weight:800;color:#3b82f6;margin:0 0 8px}
h2{font-size:1.25rem;font-weight:600;margin:0 0 16px}
p{color:#64748b;line-height:1.6;margin:0 0 24px}
a{display:inline-block;padding:10px 24px;background:#3b82f6;color:#fff;border-radius:6px;text-decoration:none;font-weight:600}
a:hover{background:#2563eb}
</style>
</head>
<body>
<div class=\"wrap\">
<h1>403</h1>
<h2>Access Denied</h2>
<p>Automated AI crawlers and scrapers are not permitted to access <strong>{$siteName}</strong>.</p>
<a href=\"{$homeUrl}\">&larr; Return to homepage</a>
</div>
</body>
</html>";
				exit;
			}
		}
	}

	// ── Default robots.txt ────────────────────────────────────────────────────

	private function getDefaultRobotsTxt(): string {
		$siteUrl = site_url( '/' );

		return implode(
			"\n",
			array(
				'User-agent: *',
				'Disallow: /wp-admin/',
				'Allow: /wp-admin/admin-ajax.php',
				'',
				'Sitemap: ' . $siteUrl . 'sitemap.xml',
				'Sitemap: ' . $siteUrl . 'news-sitemap.xml',
				'',
			)
		);
	}
}
