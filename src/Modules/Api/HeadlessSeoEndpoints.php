<?php

namespace AmEveryWhere\Modules\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HeadlessSeoEndpoints
 *
 * Provides clean REST endpoints for headless WordPress deployments:
 *   GET  /ameverywhere/v1/seo/{post_id}  — all SEO meta for a post
 *   PUT  /ameverywhere/v1/seo/{post_id}  — update SEO meta
 *   GET  /ameverywhere/v1/seo/global     — site-wide SEO defaults
 *
 * BL-027
 */
class HeadlessSeoEndpoints {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		// GET/PUT /seo/{post_id}
		register_rest_route(
			'ameverywhere/v1',
			'/seo/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getPostSeo' ),
					'permission_callback' => '__return_true',  // public read — SEO data is public
					'args'                => array( 'post_id' => array( 'sanitize_callback' => 'absint' ) ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'updatePostSeo' ),
					'permission_callback' => fn( \WP_REST_Request $request ) => current_user_can( 'edit_post', (int) $request->get_param( 'post_id' ) ),
					'args'                => array(
						'post_id' => array(
							'sanitize_callback' => 'absint',
							'validate_callback' => fn( $value ) => (int) $value > 0,
						),
					),
				),
			)
		);

		// GET /seo/global
		register_rest_route(
			'ameverywhere/v1',
			'/seo/global',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getGlobalSeo' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	// ── GET /seo/{post_id} ────────────────────────────────────────────────────

	public function getPostSeo( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$postId = (int) $request->get_param( 'post_id' );
		$post   = get_post( $postId );

		if ( ! $post || $post->post_status !== 'publish' ) {
			return new \WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response( $this->buildPostSeoPayload( $postId ) );
	}

	private function buildPostSeoPayload( int $postId ): array {
		$post   = get_post( $postId );
		$schema = ( new \AmEveryWhere\Modules\Schema\SchemaGenerator() )->getSchemaForPost( $postId );

		return array(
			'post_id'          => $postId,
			'post_type'        => $post->post_type,
			'url'              => get_permalink( $postId ),
			'meta_title'       => (string) get_post_meta( $postId, '_ameverywhere_meta_title', true ) ?: $post->post_title,
			'meta_description' => (string) get_post_meta( $postId, '_ameverywhere_meta_description', true ),
			'focus_keyword'    => (string) get_post_meta( $postId, '_ameverywhere_focus_keyword', true ),
			'canonical_url'    => (string) get_post_meta( $postId, '_ameverywhere_canonical_url', true ) ?: get_permalink( $postId ),
			'robots'           => array(
				'noindex'  => get_post_meta( $postId, '_ameverywhere_noindex', true ) === 'yes',
				'nofollow' => get_post_meta( $postId, '_ameverywhere_nofollow', true ) === 'yes',
			),
			'open_graph'       => array(
				'title'       => (string) get_post_meta( $postId, '_ameverywhere_og_title', true ),
				'description' => (string) get_post_meta( $postId, '_ameverywhere_og_description', true ),
				'image'       => (string) get_post_meta( $postId, '_ameverywhere_og_image', true ),
			),
			'twitter_card'     => array(
				'title'       => (string) get_post_meta( $postId, '_ameverywhere_twitter_title', true ),
				'description' => (string) get_post_meta( $postId, '_ameverywhere_twitter_description', true ),
				'image'       => (string) get_post_meta( $postId, '_ameverywhere_twitter_image', true ),
			),
			'schema_type'      => (string) get_post_meta( $postId, '_ameverywhere_schema_type', true ),
			'is_cornerstone'   => get_post_meta( $postId, '_ameverywhere_is_cornerstone', true ) === 'yes',
			'ai_generated'     => get_post_meta( $postId, '_ameverywhere_ai_generated', true ) === 'yes',
			'search_intent'    => (string) get_post_meta( $postId, '_ameverywhere_search_intent', true ),
			'structured_data'  => $schema ?: null,
			'modified'         => $post->post_modified,
		);
	}

	// ── PUT /seo/{post_id} ────────────────────────────────────────────────────

	public function updatePostSeo( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$postId = (int) $request->get_param( 'post_id' );
		$post   = get_post( $postId );

		if ( ! $post ) {
			return new \WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $postId ) ) {
			return new \WP_Error( 'forbidden', 'You do not have permission to edit this post.', array( 'status' => 403 ) );
		}

		$params  = $request->get_json_params();
		$updated = array();

		$stringFields = array(
			'meta_title'       => '_ameverywhere_meta_title',
			'meta_description' => '_ameverywhere_meta_description',
			'focus_keyword'    => '_ameverywhere_focus_keyword',
			'canonical_url'    => '_ameverywhere_canonical_url',
			'schema_type'      => '_ameverywhere_schema_type',
		);

		foreach ( $stringFields as $param => $metaKey ) {
			if ( array_key_exists( $param, $params ) ) {
				update_post_meta( $postId, $metaKey, sanitize_text_field( $params[ $param ] ) );
				$updated[] = $param;
			}
		}

		if ( isset( $params['robots']['noindex'] ) ) {
			update_post_meta( $postId, '_ameverywhere_noindex', $params['robots']['noindex'] ? 'yes' : 'no' );
			$updated[] = 'robots.noindex';
		}
		if ( isset( $params['robots']['nofollow'] ) ) {
			update_post_meta( $postId, '_ameverywhere_nofollow', $params['robots']['nofollow'] ? 'yes' : 'no' );
			$updated[] = 'robots.nofollow';
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'updated_fields' => $updated,
				'post_id'        => $postId,
			)
		);
	}

	// ── GET /seo/global ───────────────────────────────────────────────────────

	public function getGlobalSeo( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'site_name'        => get_bloginfo( 'name' ),
				'site_description' => get_bloginfo( 'description' ),
				'site_url'         => site_url(),
				'home_url'         => home_url(),
				'language'         => get_bloginfo( 'language' ),
				'charset'          => get_bloginfo( 'charset' ),
				'sitemap_url'      => home_url( '/sitemap.xml' ),
				'robots_txt_url'   => home_url( '/robots.txt' ),
				'llms_txt_url'     => home_url( '/llms.txt' ),
				'schema_defaults'  => array(
					'author'       => get_bloginfo( 'name' ),
					'publisher'    => get_bloginfo( 'name' ),
					'logo'         => get_site_icon_url(),
					'type_default' => get_option( 'ameverywhere_default_schema_type', 'Article' ),
				),
				'version'          => AMEVERYWHERE_VERSION ?? '1.0.0',
			)
		);
	}
}
