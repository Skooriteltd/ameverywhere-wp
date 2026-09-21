<?php

namespace AmEveryWhere\Modules\ContentAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * StaleCornerStoneDetector
 *
 * Monitors cornerstone posts for staleness (not updated within N days).
 * Emits admin notices, emails the site admin, and provides REST endpoints
 * for listing stale posts and marking them as refreshed.
 *
 * BL-022
 */
class StaleCornerStoneDetector {

	private const STALE_IDS_OPTION     = 'ameverywhere_stale_cornerstone_ids';
	private const THRESHOLD_OPTION     = 'ameverywhere_cornerstone_stale_days';
	private const DISMISSED_OPTION     = 'ameverywhere_cornerstone_notice_dismissed';
	private const CRON_HOOK            = 'ameverywhere_cornerstone_staleness_check';
	private const CORNERSTONE_META_KEY = '_ameverywhere_is_cornerstone';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
		add_action( 'admin_notices', array( $this, 'showAdminNotice' ) );
		add_action( self::CRON_HOOK, array( $this, 'checkStaleness' ) );
		add_action( 'wp_ajax_ameverywhere_dismiss_cornerstone_notice', array( $this, 'dismissNotice' ) );

		// Schedule weekly check
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::CRON_HOOK );
		}
	}

	// ── Staleness check (cron) ────────────────────────────────────────────────

	public function checkStaleness(): void {
		$thresholdDays = (int) get_option( self::THRESHOLD_OPTION, 180 );
		$threshold     = strtotime( "-{$thresholdDays} days" );

		$query = new \WP_Query(
			array(
				'post_type'      => get_post_types( array( 'public' => true ) ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::CORNERSTONE_META_KEY,
						'value' => 'yes',
					),
				),
			)
		);

		$currentStale = (array) get_option( self::STALE_IDS_OPTION, array() );
		$newlyStale   = array();
		$nowStale     = array();

		foreach ( $query->posts as $post ) {
			$modified = strtotime( $post->post_modified );
			if ( $modified < $threshold ) {
				$nowStale[] = $post->ID;
				if ( ! in_array( $post->ID, $currentStale, true ) ) {
					$newlyStale[] = $post->ID;
				}
			}
		}

		update_option( self::STALE_IDS_OPTION, $nowStale );

		// Email admin about newly stale posts
		if ( ! empty( $newlyStale ) ) {
			$this->sendStaleEmail( $newlyStale );
		}
	}

	private function sendStaleEmail( array $postIds ): void {
		$adminEmail = get_option( 'admin_email' );
		$subject    = '[AmEveryWhere] Cornerstone Content Needs Refresh';
		$body       = "<h2>Stale Cornerstone Content Alert</h2><p>The following cornerstone posts haven't been updated recently:</p><ul>";

		foreach ( $postIds as $id ) {
			$body .= '<li><a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a> (last modified: ' . get_the_modified_date( 'Y-m-d', $id ) . ')</li>';
		}

		$body .= '</ul><p><a href="' . esc_url( admin_url( 'admin.php?page=ameverywhere' ) ) . '">Open AmEveryWhere Dashboard →</a></p>';

		wp_mail( $adminEmail, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	// ── Admin notice ──────────────────────────────────────────────────────────

	public function showAdminNotice(): void {
		$stale = (array) get_option( self::STALE_IDS_OPTION, array() );
		if ( empty( $stale ) ) {
			return;
		}

		$dismissed = (bool) get_user_meta( get_current_user_id(), self::DISMISSED_OPTION, true );
		if ( $dismissed ) {
			return;
		}

		$count = count( $stale );
		$nonce = wp_create_nonce( 'ameverywhere_dismiss_cornerstone' );
		echo '<div class="notice notice-warning is-dismissible ameverywhere-cornerstone-notice" data-nonce="' . esc_attr( $nonce ) . '">';
		echo '<p><strong>AmEveryWhere:</strong> ' . esc_html( sprintf( _n( '%d cornerstone post is stale', '%d cornerstone posts are stale', $count, 'ameverywhere' ), $count ) ) . '. ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=ameverywhere#cornerstone' ) ) . '">Review and refresh →</a></p>';
		echo '</div>';

		// Inline JS to handle dismissal
		echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var el = document.querySelector(".ameverywhere-cornerstone-notice");
            if (!el) return;
            el.addEventListener("click", function(e) {
                if (e.target.classList.contains("notice-dismiss")) {
                    fetch(ajaxurl, { method: "POST", headers: {"Content-Type": "application/x-www-form-urlencoded"},
                        body: "action=ameverywhere_dismiss_cornerstone_notice&_ajax_nonce=" + el.dataset.nonce });
                }
            });
        });
        </script>';
	}

	public function dismissNotice(): void {
		check_ajax_referer( 'ameverywhere_dismiss_cornerstone' );
		update_user_meta( get_current_user_id(), self::DISMISSED_OPTION, true );
		wp_die();
	}

	// ── REST routes ───────────────────────────────────────────────────────────

	public function registerRoutes(): void {
		$editorCap = fn() => current_user_can( 'edit_posts' );
		$adminCap  = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'ameverywhere/v1',
			'/content/stale-cornerstone',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'listStale' ),
				'permission_callback' => $editorCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/content/stale-cornerstone/mark-refreshed',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'markRefreshed' ),
				'permission_callback' => $editorCap,
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/settings/cornerstone-stale-threshold',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn() => rest_ensure_response( array( 'threshold_days' => (int) get_option( self::THRESHOLD_OPTION, 180 ) ) ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => function ( \WP_REST_Request $req ) {
						$days = max( 30, (int) ( $req->get_json_params()['threshold_days'] ?? 180 ) );
						update_option( self::THRESHOLD_OPTION, $days );
						return rest_ensure_response(
							array(
								'success'        => true,
								'threshold_days' => $days,
							)
						);
					},
					'permission_callback' => $adminCap,
				),
			)
		);
	}

	public function listStale( \WP_REST_Request $request ): \WP_REST_Response {
		$staleIds = (array) get_option( self::STALE_IDS_OPTION, array() );
		$posts    = array();

		foreach ( $staleIds as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$posts[] = array(
				'id'            => $post->ID,
				'title'         => $post->post_title,
				'url'           => get_permalink( $post->ID ),
				'post_type'     => $post->post_type,
				'last_modified' => $post->post_modified,
				'days_stale'    => (int) floor( ( time() - strtotime( $post->post_modified ) ) / DAY_IN_SECONDS ),
			);
		}

		return rest_ensure_response(
			array(
				'stale_posts'    => $posts,
				'threshold_days' => (int) get_option( self::THRESHOLD_OPTION, 180 ),
				'last_check'     => wp_next_scheduled( self::CRON_HOOK ),
			)
		);
	}

	public function markRefreshed( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$postId = absint( $params['post_id'] ?? 0 );

		if ( ! $postId || ! get_post( $postId ) ) {
			return new \WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		// Touch the post modified date
		wp_update_post(
			array(
				'ID'                => $postId,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			)
		);

		// Remove from stale list
		$staleIds = (array) get_option( self::STALE_IDS_OPTION, array() );
		update_option( self::STALE_IDS_OPTION, array_values( array_diff( $staleIds, array( $postId ) ) ) );

		return rest_ensure_response( array( 'success' => true ) );
	}
}
