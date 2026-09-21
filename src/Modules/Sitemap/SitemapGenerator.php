<?php

namespace AmEveryWhere\Modules\Sitemap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates a Sitemap Index and date-based sub-sitemaps.
 *
 * Architecture:
 *   /sitemap.xml           → <sitemapindex> listing all YYYY-MM sub-sitemaps
 *   /sitemap-posts-2024-05.xml → <urlset> with posts from May 2024
 *
 * Performance:
 *   - Uses raw $wpdb queries joining wp_postmeta to pre-filter noindex in a single pass.
 *   - Each sub-sitemap is capped at 1000 URLs and cached independently via transients.
 *   - Eliminates the N+1 query storm from the old get_posts + get_post_meta loop.
 */
class SitemapGenerator {

	private const INDEX_TRANSIENT        = 'ameverywhere_sitemap_index_cache';
	private const CHUNK_TRANSIENT_PREFIX = 'ameverywhere_sitemap_chunk_';
	private const CACHE_EXPIRATION       = 4 * HOUR_IN_SECONDS;
	private const MAX_URLS_PER_CHUNK     = 1000;

	/**
	 * Serve the sitemap index or a date-based chunk.
	 *
	 * @param string|null $chunk  e.g. '2024-05' or null for the index.
	 */
	public function serveSitemap( ?string $chunk = null ): void {
		header( 'Content-Type: text/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow', true );

		if ( $chunk === null ) {
			echo $this->getOrBuildIndex(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo $this->getOrBuildChunk( $chunk ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Clear all sitemap caches (call when posts are updated or settings change).
	 */
	public static function clearCache(): void {
		delete_transient( self::INDEX_TRANSIENT );

		// Purge all chunk transients — the cleanest way without tracking keys
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
				'_transient_' . self::CHUNK_TRANSIENT_PREFIX . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
				'_transient_timeout_' . self::CHUNK_TRANSIENT_PREFIX . '%'
			)
		);
	}

	// ──────────────────────────────────────────────────────────────────
	// Sitemap Index
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Build or fetch the cached <sitemapindex>.
	 */
	private function getOrBuildIndex(): string {
		$xml = get_transient( self::INDEX_TRANSIENT );
		if ( $xml !== false ) {
			return $xml;
		}

		$xml = $this->buildIndex();
		set_transient( self::INDEX_TRANSIENT, $xml, self::CACHE_EXPIRATION );
		return $xml;
	}

	/**
	 * Generate the sitemapindex XML listing all date-based sub-sitemaps.
	 */
	private function buildIndex(): string {
		global $wpdb;

		$postTypes    = $this->getActivePostTypes();
		$placeholders = implode( ',', array_fill( 0, count( $postTypes ), '%s' ) );

		// Get distinct YYYY-MM periods that contain published, non-noindex posts
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$periods = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE_FORMAT(p.post_date, '%%Y-%%m') AS period
                 FROM $wpdb->posts p
                 LEFT JOIN $wpdb->postmeta pm
                   ON p.ID = pm.post_id AND pm.meta_key = '_ameverywhere_noindex'
                 WHERE p.post_status = 'publish'
                   AND p.post_type IN ($placeholders)
                   AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
                 ORDER BY period DESC",
				...$postTypes
			)
		);

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $periods as $period ) {
			$loc  = home_url( '/sitemap-posts-' . $period . '.xml' );
			$xml .= "  <sitemap>\n";
			$xml .= '    <loc>' . esc_url( $loc ) . "</loc>\n";
			$xml .= "  </sitemap>\n";
		}

		// Include the news sitemap in the index if enabled
		if ( SitemapSettings::get( 'enable_news_sitemap', 'no' ) === 'yes' ) {
			$xml .= "  <sitemap>\n";
			$xml .= '    <loc>' . esc_url( home_url( '/sitemap-news.xml' ) ) . "</loc>\n";
			$xml .= "  </sitemap>\n";
		}

		// Include the video sitemap in the index if enabled
		if ( SitemapSettings::get( 'enable_video_sitemap', 'no' ) === 'yes' ) {
			$xml .= "  <sitemap>\n";
			$xml .= '    <loc>' . esc_url( home_url( '/video-sitemap.xml' ) ) . "</loc>\n";
			$xml .= "  </sitemap>\n";
		}

		$xml .= '</sitemapindex>';
		return $xml;
	}

	// ──────────────────────────────────────────────────────────────────
	// Date-Based Sub-Sitemap Chunks
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Build or fetch a cached date chunk (e.g. '2024-05').
	 */
	private function getOrBuildChunk( string $period ): string {
		// Validate period format (YYYY-MM)
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $period ) ) {
			return $this->empty404Xml();
		}

		$transientKey = self::CHUNK_TRANSIENT_PREFIX . $period;
		$xml          = get_transient( $transientKey );
		if ( $xml !== false ) {
			return $xml;
		}

		$xml = $this->buildChunk( $period );
		set_transient( $transientKey, $xml, self::CACHE_EXPIRATION );
		return $xml;
	}

	/**
	 * Generate the <urlset> XML for a specific YYYY-MM period.
	 * Uses a single optimized query joining postmeta.
	 */
	private function buildChunk( string $period ): string {
		global $wpdb;

		$postTypes    = $this->getActivePostTypes();
		$placeholders = implode( ',', array_fill( 0, count( $postTypes ), '%s' ) );

		$excludePostIds = $this->getExcludedPostIds();
		$excludeClause  = '';
		if ( ! empty( $excludePostIds ) ) {
			$excludePlaceholders = implode( ',', array_fill( 0, count( $excludePostIds ), '%d' ) );
			$excludeClause       = "AND p.ID NOT IN ($excludePlaceholders)";
		}

		$yearMonth = $period . '-01';

		// Single-pass query: joins postmeta to filter noindex, fetches thumbnail ID
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_type, p.post_modified_gmt,
                    thumb.meta_value AS thumbnail_id
             FROM $wpdb->posts p
             LEFT JOIN $wpdb->postmeta pm
               ON p.ID = pm.post_id AND pm.meta_key = '_ameverywhere_noindex'
             LEFT JOIN $wpdb->postmeta thumb
               ON p.ID = thumb.post_id AND thumb.meta_key = '_thumbnail_id'
             WHERE p.post_status = 'publish'
               AND p.post_type IN ($placeholders)
               AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
               AND YEAR(p.post_date) = YEAR(%s)
               AND MONTH(p.post_date) = MONTH(%s)
               $excludeClause
             ORDER BY p.post_modified_gmt DESC
             LIMIT %d",
			...array_merge(
				$postTypes,
				array( $yearMonth, $yearMonth ),
				$excludePostIds,
				array( self::MAX_URLS_PER_CHUNK )
			)
		);

		$rows = $wpdb->get_results( $query );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		$xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

		$frontPageId = (int) get_option( 'page_on_front' );

		foreach ( $rows as $row ) {
			// Skip the front page — it is added via the homepage entry in the index
			if ( (int) $row->ID === $frontPageId ) {
				continue;
			}

			$url      = get_permalink( $row->ID );
			$modified = mysql2date( 'c', $row->post_modified_gmt );

			// BL-015: per-post-type and per-URL priority/changefreq via SitemapPriorityManager
			if ( \AmEveryWhere\Modules\Sitemap\SitemapPriorityManager::isExcluded( (int) $row->ID, $row->post_type ) ) {
				continue;
			}

			$priority   = \AmEveryWhere\Modules\Sitemap\SitemapPriorityManager::getPriority( (int) $row->ID, $row->post_type );
			$changefreq = \AmEveryWhere\Modules\Sitemap\SitemapPriorityManager::getChangefreq( (int) $row->ID, $row->post_type );

			$xml .= "  <url>\n";
			$xml .= '    <loc>' . esc_url( $url ) . "</loc>\n";
			$xml .= '    <lastmod>' . esc_html( $modified ) . "</lastmod>\n";
			$xml .= '    <changefreq>' . esc_html( $changefreq ) . "</changefreq>\n";
			$xml .= '    <priority>' . esc_html( $priority ) . "</priority>\n";

			// Thumbnail image — fetched via JOIN, no extra query
			if ( ! empty( $row->thumbnail_id ) ) {
				$imageUrl = wp_get_attachment_url( (int) $row->thumbnail_id );
				if ( $imageUrl ) {
					$xml .= "    <image:image>\n";
					$xml .= '      <image:loc>' . esc_url( $imageUrl ) . "</image:loc>\n";
					$xml .= "    </image:image>\n";
				}
			}

			$xml .= "  </url>\n";
		}

		$xml .= '</urlset>';
		return $xml;
	}

	// ──────────────────────────────────────────────────────────────────
	// Helpers
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Return the list of public post types minus any user-excluded types.
	 */
	private function getActivePostTypes(): array {
		$postTypes = get_post_types( array( 'public' => true ) );
		$excluded  = SitemapSettings::get( 'sitemap_exclude_types', array() );

		if ( is_array( $excluded ) ) {
			$postTypes = array_diff( $postTypes, $excluded );
		}

		return empty( $postTypes ) ? array( 'post', 'page' ) : array_values( $postTypes );
	}

	/**
	 * Parse the comma-separated list of manually excluded post IDs.
	 */
	private function getExcludedPostIds(): array {
		$raw = SitemapSettings::get( 'sitemap_exclude_posts', '' );
		if ( empty( $raw ) ) {
			return array();
		}
		return array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
	}

	/**
	 * Return a valid but empty urlset for invalid chunk requests.
	 */
	private function empty404Xml(): string {
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
	}
}
