<?php

namespace AmEveryWhere\Modules\Sitemap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AmEveryWhere\Core\Event\EventManager;

/**
 * Boots the sitemap module, registers REST endpoints for sitemaps config,
 * and attaches hooks to clear sitemap cache on post mutations.
 */
class SitemapModule {

	private EventManager $eventManager;
	private SitemapRouteManager $routeManager;

	public function __construct( EventManager $eventManager, SitemapRouteManager $routeManager ) {
		$this->eventManager = $eventManager;
		$this->routeManager = $routeManager;
	}

	public function boot(): void {
		// Core sitemaps remain enabled unless an administrator explicitly
		// enables this plugin's sitemap provider. This avoids silently
		// disrupting established SEO tooling on activation.
		$this->eventManager->addFilter( 'wp_sitemaps_enabled', array( $this, 'coreSitemapsEnabled' ) );

		// Add custom rewrite rules
		$this->eventManager->addAction( 'init', array( $this->routeManager, 'addRewriteRules' ) );

		// Register custom query vars for date-based sub-sitemap routing
		$this->eventManager->addFilter( 'query_vars', array( $this->routeManager, 'registerQueryVars' ) );

		// Handle virtual sitemap requests
		$this->eventManager->addAction( 'template_redirect', array( $this->routeManager, 'handleSitemapRequests' ), 0 );

		// Ensure rewrite rules are flushed on activation
		$this->eventManager->addAction( 'ameverywhere_activation', array( $this->routeManager, 'flushRules' ) );

		// Clear sitemap cache when content changes
		$this->eventManager->addAction( 'save_post', array( SitemapGenerator::class, 'clearCache' ) );
		$this->eventManager->addAction( 'deleted_post', array( SitemapGenerator::class, 'clearCache' ) );
		$this->eventManager->addAction( 'transition_post_status', array( SitemapGenerator::class, 'clearCache' ) );

		$this->eventManager->addAction( 'save_post', array( VideoSitemapGenerator::class, 'clearCache' ) );
		$this->eventManager->addAction( 'deleted_post', array( VideoSitemapGenerator::class, 'clearCache' ) );
		$this->eventManager->addAction( 'transition_post_status', array( VideoSitemapGenerator::class, 'clearCache' ) );

		// Register configuration routes
		$this->eventManager->addAction( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Register REST API routes for sitemap settings.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			'ameverywhere/v1',
			'/settings/sitemaps',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getSitemapSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'updateSitemapSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);
	}

	public function checkPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	private function isPluginSitemapEnabled(): bool {
		return SitemapSettings::get( 'enable_index_sitemap', 'no' ) === 'yes';
	}

	public function coreSitemapsEnabled( bool $enabled ): bool {
		return $this->isPluginSitemapEnabled() ? false : $enabled;
	}

	/**
	 * Get sitemap configuration.
	 */
	public function getSitemapSettings( \WP_REST_Request $request ): \WP_REST_Response {
		// Get all public post types for checkboxes in frontend
		$allPublicTypes = get_post_types( array( 'public' => true ), 'objects' );
		$typesList      = array();
		foreach ( $allPublicTypes as $type ) {
			$typesList[] = array(
				'name'  => $type->name,
				'label' => $type->label ?: $type->name,
			);
		}

		return rest_ensure_response(
			array(
				'enable_index_sitemap'    => $this->isPluginSitemapEnabled(),
				'enable_news_sitemap'     => SitemapSettings::get( 'enable_news_sitemap', 'no' ) === 'yes',
				'enable_video_sitemap'    => SitemapSettings::get( 'enable_video_sitemap', 'no' ) === 'yes',
				'exclude_types'           => SitemapSettings::get( 'sitemap_exclude_types', array() ),
				'exclude_posts'           => SitemapSettings::get( 'sitemap_exclude_posts', '' ),
				'sitemap_changefreq_post' => SitemapSettings::get( 'sitemap_changefreq_post', 'weekly' ),
				'sitemap_changefreq_page' => SitemapSettings::get( 'sitemap_changefreq_page', 'weekly' ),
				'sitemap_priority_post'   => SitemapSettings::get( 'sitemap_priority_post', '0.6' ),
				'sitemap_priority_page'   => SitemapSettings::get( 'sitemap_priority_page', '0.8' ),
				'available_types'         => $typesList,
			)
		);
	}

	/**
	 * Update sitemap configuration.
	 */
	public function updateSitemapSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		if ( isset( $params['enable_index_sitemap'] ) ) {
			SitemapSettings::set( 'enable_index_sitemap', $params['enable_index_sitemap'] ? 'yes' : 'no' );
		}

		if ( isset( $params['enable_news_sitemap'] ) ) {
			SitemapSettings::set( 'enable_news_sitemap', $params['enable_news_sitemap'] ? 'yes' : 'no' );
		}

		if ( isset( $params['enable_video_sitemap'] ) ) {
			SitemapSettings::set( 'enable_video_sitemap', $params['enable_video_sitemap'] ? 'yes' : 'no' );
		}

		if ( isset( $params['exclude_types'] ) && is_array( $params['exclude_types'] ) ) {
			$cleaned = array_map( 'sanitize_text_field', $params['exclude_types'] );
			SitemapSettings::set( 'sitemap_exclude_types', $cleaned );
		}

		if ( isset( $params['exclude_posts'] ) ) {
			SitemapSettings::set( 'sitemap_exclude_posts', sanitize_text_field( $params['exclude_posts'] ) );
		}

		if ( isset( $params['sitemap_changefreq_post'] ) ) {
			SitemapSettings::set( 'sitemap_changefreq_post', sanitize_text_field( $params['sitemap_changefreq_post'] ) );
		}

		if ( isset( $params['sitemap_changefreq_page'] ) ) {
			SitemapSettings::set( 'sitemap_changefreq_page', sanitize_text_field( $params['sitemap_changefreq_page'] ) );
		}

		if ( isset( $params['sitemap_priority_post'] ) ) {
			SitemapSettings::set( 'sitemap_priority_post', sanitize_text_field( $params['sitemap_priority_post'] ) );
		}

		if ( isset( $params['sitemap_priority_page'] ) ) {
			SitemapSettings::set( 'sitemap_priority_page', sanitize_text_field( $params['sitemap_priority_page'] ) );
		}

		// Clear transient cache so new rules apply immediately
		SitemapGenerator::clearCache();
		VideoSitemapGenerator::clearCache();
		$this->routeManager->removeRulesAndFlush();
		if ( $this->isPluginSitemapEnabled() ) {
			$this->routeManager->flushRules();
		}

		return rest_ensure_response( array( 'success' => true ) );
	}
}
