<?php

namespace AmEveryWhere\Modules\Sitemap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AmEveryWhere\Modules\Schema\VideoExtractor;

/**
 * Generates and caches the Google-compliant Video XML sitemap.
 * Implements high-performance Transient caching.
 */
class VideoSitemapGenerator {

	private const CACHE_TRANSIENT  = 'ameverywhere_video_sitemap_xml_cache';
	private const CACHE_EXPIRATION = 4 * HOUR_IN_SECONDS;

	/**
	 * Serves the virtual video-sitemap.xml.
	 */
	public function serveSitemap(): void {
		header( 'Content-Type: text/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow', true );

		$xml = get_transient( self::CACHE_TRANSIENT );
		if ( $xml === false ) {
			$xml = $this->generateXml();
			set_transient( self::CACHE_TRANSIENT, $xml, self::CACHE_EXPIRATION );
		}

		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Clears sitemap cache.
	 */
	public static function clearCache(): void {
		delete_transient( self::CACHE_TRANSIENT );
	}

	/**
	 * Compiles Google-compliant XML structure.
	 */
	private function generateXml(): string {
		$excludePostTypes   = SitemapSettings::get( 'sitemap_exclude_types', array() );
		$excludePostsString = SitemapSettings::get( 'sitemap_exclude_posts', '' );

		$excludePostIds = array();
		if ( ! empty( $excludePostsString ) ) {
			$excludePostIds = array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $excludePostsString ) ) ) );
		}

		// Get public post types
		$postTypes = get_post_types( array( 'public' => true ) );
		if ( is_array( $excludePostTypes ) ) {
			$postTypes = array_diff( $postTypes, $excludePostTypes );
		}

		if ( empty( $postTypes ) ) {
			$postTypes = array( 'post', 'page' );
		}

		$queryArgs = array(
			'post_type'      => array_values( $postTypes ),
			'post_status'    => 'publish',
			'posts_per_page' => 500, // Capped to maintain speed
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		if ( ! empty( $excludePostIds ) ) {
			$queryArgs['post__not_in'] = $excludePostIds;
		}

		$posts = get_posts( $queryArgs );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		$xml .= '        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

		$videoCount = 0;

		foreach ( $posts as $post ) {
			// Check if there is a noindex tag
			$noindex = get_post_meta( $post->ID, '_ameverywhere_noindex', true );
			if ( $noindex === 'yes' ) {
				continue;
			}

			// Extract videos
			$videos = VideoExtractor::extractVideoObjects( $post->ID );
			if ( empty( $videos ) ) {
				continue;
			}

			$url = get_permalink( $post->ID );

			foreach ( $videos as $video ) {
				$xml .= "  <url>\n";
				$xml .= '    <loc>' . esc_url( $url ) . "</loc>\n";
				$xml .= "    <video:video>\n";
				$xml .= '      <video:thumbnail_loc>' . esc_url( $video['thumbnailUrl'] ) . "</video:thumbnail_loc>\n";
				$xml .= '      <video:title>' . esc_html( $video['name'] ) . "</video:title>\n";
				$xml .= '      <video:description>' . esc_html( $video['description'] ) . "</video:description>\n";

				// If it is a direct file url (like .mp4) use content_loc, otherwise use player_loc (embed url)
				$embedUrl = $video['embedUrl'];
				if ( preg_match( '/\.(mp4|m4v|webm|ogv)$/i', $embedUrl ) ) {
					$xml .= '      <video:content_loc>' . esc_url( $embedUrl ) . "</video:content_loc>\n";
				} else {
					$xml .= '      <video:player_loc>' . esc_url( $embedUrl ) . "</video:player_loc>\n";
				}

				$durationSec = 60; // fallback
				if ( preg_match( '/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $video['duration'], $dm ) ) {
					$hours       = isset( $dm[1] ) ? intval( $dm[1] ) : 0;
					$mins        = isset( $dm[2] ) ? intval( $dm[2] ) : 0;
					$secs        = isset( $dm[3] ) ? intval( $dm[3] ) : 0;
					$durationSec = ( $hours * 3600 ) + ( $mins * 60 ) + $secs;
				}

				$xml .= '      <video:duration>' . $durationSec . "</video:duration>\n";
				$xml .= '      <video:publication_date>' . esc_html( $video['uploadDate'] ) . "</video:publication_date>\n";
				$xml .= "    </video:video>\n";
				$xml .= "  </url>\n";
				++$videoCount;
			}
		}

		// Add a blank placeholder if absolutely no videos are found, to prevent empty XML parsing crash
		if ( $videoCount === 0 ) {
			$xml .= "  <url>\n";
			$xml .= '    <loc>' . esc_url( home_url( '/' ) ) . "</loc>\n";
			$xml .= "  </url>\n";
		}

		$xml .= '</urlset>';
		return $xml;
	}
}
