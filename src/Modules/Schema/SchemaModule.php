<?php

namespace AmEveryWhere\Modules\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AmEveryWhere\Core\Event\EventManager;

/**
 * Boots the Schema module and registers REST API endpoints.
 */
class SchemaModule {

	private EventManager $eventManager;
	private SchemaGenerator $schemaGenerator;

	public function __construct( EventManager $eventManager, SchemaGenerator $schemaGenerator ) {
		$this->eventManager    = $eventManager;
		$this->schemaGenerator = $schemaGenerator;
	}

	public function boot(): void {
		$this->eventManager->addAction( 'wp_footer', array( $this->schemaGenerator, 'outputSchema' ), 10 );
		$this->eventManager->addAction( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRoutes(): void {
		// Public machine-readable endpoint for LLM system ingestion
		register_rest_route(
			'ameverywhere/v1',
			'/schemamap',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handleSchemaMap' ),
				'permission_callback' => '__return_true', // Publicly accessible
			)
		);

		// Competitor URL scraper proxy (Authenticated)
		register_rest_route(
			'ameverywhere/v1',
			'/schema/scrape',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handleScrapeCompetitor' ),
				'permission_callback' => array( $this, 'checkEditorPermission' ),
			)
		);

		// Read/Write global conditional schema display rules (Admin only)
		register_rest_route(
			'ameverywhere/v1',
			'/schema/global-rules',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getGlobalRules' ),
					'permission_callback' => array( $this, 'checkAdminPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'updateGlobalRules' ),
					'permission_callback' => array( $this, 'checkAdminPermission' ),
				),
			)
		);
	}

	public function checkEditorPermission(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handles /wp-json/ameverywhere/v1/schemamap
	 */
	public function handleSchemaMap( \WP_REST_Request $request ): \WP_REST_Response {
		$postIdParam = $request->get_param( 'post_id' );
		$urlParam    = $request->get_param( 'url' );

		$postId = 0;
		if ( ! empty( $postIdParam ) ) {
			$postId = intval( $postIdParam );
		} elseif ( ! empty( $urlParam ) ) {
			$postId = url_to_postid( esc_url_raw( $urlParam ) );
		}

		if ( $postId > 0 ) {
			$post = get_post( $postId );
			if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'inherit' ), true ) ) {
				return new \WP_Error( 'post_not_found', __( 'Specified post was not found or is not published.', 'ameverywhere' ), array( 'status' => 404 ) );
			}

			$schema = $this->schemaGenerator->getSchemaForPost( $postId );
			return rest_ensure_response( $schema );
		}

		// Return a site-wide mapping list for LLMs
		$postTypes = get_post_types( array( 'public' => true ) );
		$query     = new \WP_Query(
			array(
				'post_type'      => array_values( $postTypes ),
				'post_status'    => 'publish',
				'posts_per_page' => 100, // Capped to protect performance
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$posts = array();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$id              = get_the_ID();
				$effectiveSchema = SchemaGenerator::getEffectivePrimarySchema( $id );
				$schemas         = array( 'BreadcrumbList' ); // Default
				$isNews          = get_post_meta( $id, '_ameverywhere_is_news', true );
				$schemas[]       = ( $isNews === 'yes' ) ? 'NewsArticle' : 'Article';

				if ( $effectiveSchema !== 'none' ) {
					// map slug to actual schema name
					$schemas[] = ucfirst( $effectiveSchema );
				}

				// Check video Objects
				$videoObjects = VideoExtractor::extractVideoObjects( $id );
				if ( ! empty( $videoObjects ) ) {
					$schemas[] = 'VideoObject';
				}

				$posts[] = array(
					'id'            => $id,
					'title'         => get_the_title(),
					'url'           => get_permalink(),
					'schemas'       => array_unique( $schemas ),
					'schemamap_url' => rest_url( "ameverywhere/v1/schemamap?post_id={$id}" ),
				);
			}
			wp_reset_postdata();
		}

		return rest_ensure_response(
			array(
				'posts' => $posts,
			)
		);
	}

	/**
	 * Handles /wp-json/ameverywhere/v1/schema/scrape
	 */
	public function handleScrapeCompetitor( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$url    = isset( $params['url'] ) ? sanitize_text_field( $params['url'] ) : '';

		if ( empty( $url ) ) {
			return new \WP_Error( 'missing_url', __( 'URL parameter is required.', 'ameverywhere' ), array( 'status' => 400 ) );
		}

		$result = CompetitorScraper::scrapeUrl( $url );
		if ( ! $result['success'] ) {
			return new \WP_Error( 'scrape_failed', $result['message'], array( 'status' => 400 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handles GET /wp-json/ameverywhere/v1/schema/global-rules
	 */
	public function getGlobalRules( \WP_REST_Request $request ): \WP_REST_Response {
		$rulesJson = get_option( 'ameverywhere_global_schema_rules', '[]' );
		$rules     = json_decode( $rulesJson, true );
		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		// Get post types and categories for the UI selectors
		$allPublicTypes = get_post_types( array( 'public' => true ), 'objects' );
		$typesList      = array();
		foreach ( $allPublicTypes as $type ) {
			$typesList[] = array(
				'name'  => $type->name,
				'label' => $type->label ?: $type->name,
			);
		}

		$categories = get_categories( array( 'hide_empty' => false ) );
		$catsList   = array();
		foreach ( $categories as $cat ) {
			$catsList[] = array(
				'id'   => $cat->term_id,
				'name' => $cat->name,
			);
		}

		return rest_ensure_response(
			array(
				'rules'      => $rules,
				'post_types' => $typesList,
				'categories' => $catsList,
			)
		);
	}

	/**
	 * Handles POST /wp-json/ameverywhere/v1/schema/global-rules
	 */
	public function updateGlobalRules( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$rules  = isset( $params['rules'] ) ? $params['rules'] : array();

		if ( ! is_array( $rules ) ) {
			return new \WP_Error( 'invalid_format', __( 'Rules must be a valid array.', 'ameverywhere' ), array( 'status' => 400 ) );
		}

		// Sanitize
		$sanitized = array();
		foreach ( $rules as $rule ) {
			if ( empty( $rule['post_type'] ) || empty( $rule['schema_type'] ) ) {
				continue;
			}
			$sanitized[] = array(
				'post_type'   => sanitize_text_field( $rule['post_type'] ),
				'category'    => sanitize_text_field( $rule['category'] ), // e.g. "all" or term ID
				'schema_type' => sanitize_text_field( $rule['schema_type'] ),
			);
		}

		update_option( 'ameverywhere_global_schema_rules', wp_json_encode( $sanitized ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'rules'   => $sanitized,
			)
		);
	}
}
