<?php

namespace AmEveryWhere\Core\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a fallback proxy for heavy data computation and analytics API calls
 * when the user has not provided their own native API keys.
 * 
 * E.g., PageSpeed Insights, Indexing limits, GSC aggregate queries.
 */
class AmEveryWhereApiGateway {

	private const PROXY_ENDPOINT = 'https://api.ameverywhere.com/v1';

	public static function hasActiveLicense(): bool {
		return (bool) get_option( 'ameverywhere_license_key', false );
	}

	public static function proxyRequest( string $service, array $payload ): array|\WP_Error {
		$license = get_option( 'ameverywhere_license_key', '' );
		if ( empty( $license ) ) {
			return new \WP_Error( 'no_license', 'AmEveryWhere API fallback requires an active license key.' );
		}

		$response = wp_safe_remote_post(
			self::PROXY_ENDPOINT . '/' . $service,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $license,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			return new \WP_Error( 'api_error', $body['message'] ?? 'AmEveryWhere API error.' );
		}

		return $body;
	}
}
