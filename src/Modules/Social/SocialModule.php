<?php

namespace AmEveryWhere\Modules\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AmEveryWhere\Core\Event\EventManager;
use AmEveryWhere\Core\Queue\QueueManager;
use AmEveryWhere\Core\Security\KeyVault;

class SocialModule {

	private EventManager $eventManager;

	public function __construct( EventManager $eventManager ) {
		$this->eventManager = $eventManager;
	}

	public function boot(): void {
		$this->eventManager->addAction( 'rest_api_init', array( $this, 'registerRestRoutes' ) );
		$this->eventManager->addAction( 'transition_post_status', array( $this, 'handlePostTransition' ), 10, 3 );
	}

	public function handlePostTransition( $newStatus, $oldStatus, $post ): void {
		// Only trigger on first publish
		if ( $newStatus === 'publish' && $oldStatus !== 'publish' ) {

			// Check global auto-share toggle setting
			$globalAutoShare = get_option( 'ameverywhere_global_auto_share', 'yes' );
			if ( $globalAutoShare !== 'yes' ) {
				return;
			}

			// Dispatch job
			$queueManager = new QueueManager();
			$queueManager->push(
				AutoShareJob::class,
				array(
					'post_id' => $post->ID,
					'retries' => 0,
					'manual'  => false,
				)
			);
		}
	}

	public function registerRestRoutes(): void {
		register_rest_route(
			'ameverywhere/v1',
			'/social/accounts',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getAccounts' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveAccount' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/social/accounts/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'deleteAccount' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE, // PUT/PATCH
					'callback'            => array( $this, 'updateAccount' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/social/share',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'manualShare' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		// Mock OAuth callback endpoints for testing UI
		register_rest_route(
			'ameverywhere/v1',
			'/social/oauth-init',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'initOAuth' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/social/oauth-callback',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'oauthCallback' ),
				'permission_callback' => '__return_true', // Public for redirect
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/social/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getSocialSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveSocialSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);
	}

	public function checkPermission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );
	}

	public function getAccounts( \WP_REST_Request $request ): \WP_REST_Response {
		$manager  = new SocialAccountManager();
		$accounts = $manager->getAccounts();

		// Mask access tokens
		foreach ( $accounts as &$acc ) {
			if ( isset( $acc['access_token'] ) ) {
				$acc['access_token'] = '********';
			}
		}
		unset( $acc );

		$cats       = get_categories( array( 'hide_empty' => false ) );
		$categories = array_map(
			function ( $cat ) {
				return array(
					'id'   => $cat->term_id,
					'name' => $cat->name,
				);
			},
			$cats
		);

		return rest_ensure_response(
			array(
				'accounts'          => $accounts,
				'global_auto_share' => get_option( 'ameverywhere_global_auto_share', 'yes' ) === 'yes',
				'categories'        => $categories,
			)
		);
	}

	public function saveAccount( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		if ( isset( $params['global_auto_share'] ) ) {
			update_option( 'ameverywhere_global_auto_share', $params['global_auto_share'] ? 'yes' : 'no' );
			return rest_ensure_response( array( 'success' => true ) );
		}

		if ( empty( $params['network'] ) || empty( $params['access_token'] ) ) {
			return new \WP_Error( 'missing_params', 'Network and Access Token are required.', array( 'status' => 400 ) );
		}

		$manager = new SocialAccountManager();
		$manager->saveAccount(
			array(
				'network'      => sanitize_text_field( $params['network'] ),
				'access_token' => sanitize_text_field( $params['access_token'] ),
				'profile_name' => sanitize_text_field( $params['profile_name'] ?? 'Unknown Profile' ),
				'auto_share'   => isset( $params['auto_share'] ) ? (bool) $params['auto_share'] : true,
				'created_at'   => time(),
			)
		);

		return rest_ensure_response(
			array(
				'success'  => true,
				'accounts' => $manager->getAccounts(),
			)
		);
	}

	public function deleteAccount( \WP_REST_Request $request ): \WP_REST_Response {
		$id      = $request->get_param( 'id' );
		$manager = new SocialAccountManager();

		if ( $manager->deleteAccount( $id ) ) {
			return rest_ensure_response(
				array(
					'success'  => true,
					'accounts' => $manager->getAccounts(),
				)
			);
		}

		return new \WP_Error( 'not_found', 'Account not found.', array( 'status' => 404 ) );
	}

	public function updateAccount( \WP_REST_Request $request ): \WP_REST_Response {
		$id     = $request->get_param( 'id' );
		$params = $request->get_json_params();

		$manager = new SocialAccountManager();
		if ( $manager->updateAccount( $id, $params ) ) {
			return rest_ensure_response(
				array(
					'success'  => true,
					'accounts' => $manager->getAccounts(),
				)
			);
		}

		return new \WP_Error( 'not_found', 'Account not found.', array( 'status' => 404 ) );
	}

	public function manualShare( \WP_REST_Request $request ): \WP_REST_Response {
		$postId = $request->get_param( 'post_id' );
		if ( ! $postId ) {
			return new \WP_Error( 'missing_param', 'Post ID is required.', array( 'status' => 400 ) );
		}

		// We dispatch immediately via job queue (but we could also run it synchronously for immediate UI feedback,
		// since the user expects a quick 'success'. However, queue is safer. Let's just run it inline for manual trigger UI responsiveness).

		$job = new AutoShareJob(
			array(
				'post_id' => $postId,
				'retries' => 0,
				'manual'  => true,
			)
		);
		$job->handle();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Share dispatched successfully.',
			)
		);
	}

	public function initOAuth( \WP_REST_Request $request ): \WP_REST_Response {
		$params  = $request->get_json_params();
		$network = sanitize_text_field( $params['network'] ?? '' );

		if ( empty( $network ) ) {
			return new \WP_Error( 'missing_network', 'Network parameter is required.', array( 'status' => 400 ) );
		}

		$apps = get_option( 'ameverywhere_social_apps', array() );
		if ( empty( $apps[ $network ]['app_id'] ) || empty( $apps[ $network ]['app_secret'] ) ) {
			return new \WP_Error(
				'not_configured',
				sprintf( 'Please configure your %s App ID and Secret in the Platform API Settings first.', ucfirst( $network ) ),
				array( 'status' => 400 )
			);
		}

		$clientId = $apps[ $network ]['app_id'];
		$state    = function_exists( 'wp_generate_password' ) ? wp_generate_password( 32, false ) : bin2hex( random_bytes( 16 ) );

		// Store transient for 10 minutes tied to admin session
		set_transient(
			'ameverywhere_oauth_state_' . $state,
			array(
				'user_id' => get_current_user_id(),
				'network' => $network,
			),
			10 * MINUTE_IN_SECONDS
		);

		$callbackUri     = rest_url( "ameverywhere/v1/social/oauth-callback?network={$network}" );
		$encodedCallback = urlencode( $callbackUri );

		$authUrl = '';
		if ( $network === 'facebook' ) {
			$authUrl = "https://www.facebook.com/v19.0/dialog/oauth?client_id={$clientId}&redirect_uri={$encodedCallback}&state={$state}&scope=pages_manage_posts,pages_read_engagement";
		} elseif ( $network === 'twitter' ) {
			$authUrl = "https://twitter.com/i/oauth2/authorize?response_type=code&client_id={$clientId}&redirect_uri={$encodedCallback}&state={$state}&scope=tweet.read%20tweet.write%20users.read%20offline.access&code_challenge=challenge&code_challenge_method=plain";
		} elseif ( $network === 'linkedin' ) {
			$authUrl = "https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id={$clientId}&redirect_uri={$encodedCallback}&state={$state}&scope=w_member_social";
		} elseif ( $network === 'pinterest' ) {
			$authUrl = "https://www.pinterest.com/oauth/?client_id={$clientId}&redirect_uri={$encodedCallback}&response_type=code&state={$state}&scope=boards:read,pins:read,pins:write";
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'auth_url' => $authUrl,
				'state'    => $state,
			)
		);
	}

	public function oauthCallback( \WP_REST_Request $request ): \WP_REST_Response {
		$network = sanitize_text_field( $request->get_param( 'network' ) ?? '' );
		$code    = $request->get_param( 'code' );
		$error   = $request->get_param( 'error' );
		$state   = sanitize_text_field( $request->get_param( 'state' ) ?? '' );

		$redirectUrl = admin_url( 'admin.php?page=ameverywhere#/social' );

		if ( $error || ! $code || empty( $state ) ) {
			$errParam = $error ? urlencode( $error ) : 'missing_code_or_state';
			wp_redirect( add_query_arg( 'oauth_error', $errParam, $redirectUrl ) );
			exit;
		}

		// Verify state against transient
		$transientKey = 'ameverywhere_oauth_state_' . $state;
		$stateData    = get_transient( $transientKey );

		if ( empty( $stateData ) || ! is_array( $stateData ) ) {
			wp_redirect( add_query_arg( 'oauth_error', 'invalid_or_expired_state', $redirectUrl ) );
			exit;
		}

		// Delete transient immediately to prevent replay
		delete_transient( $transientKey );

		// Verify network matches initiation
		if ( ( $stateData['network'] ?? '' ) !== $network ) {
			wp_redirect( add_query_arg( 'oauth_error', 'network_mismatch', $redirectUrl ) );
			exit;
		}

		// Verify initiating user has manage_options capability
		$userId = (int) ( $stateData['user_id'] ?? 0 );
		$user   = $userId > 0 ? get_userdata( $userId ) : false;
		if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
			wp_redirect( add_query_arg( 'oauth_error', 'unauthorized', $redirectUrl ) );
			exit;
		}

		$apps = get_option( 'ameverywhere_social_apps', array() );
		if ( empty( $apps[ $network ]['app_id'] ) || empty( $apps[ $network ]['app_secret'] ) ) {
			wp_redirect( add_query_arg( 'oauth_error', 'not_configured', $redirectUrl ) );
			exit;
		}

		$appId       = $apps[ $network ]['app_id'];
		$appSecret   = KeyVault::decrypt( $apps[ $network ]['app_secret'] );
		$callbackUri = rest_url( "ameverywhere/v1/social/oauth-callback?network={$network}" );

		$accessToken = '';
		$profileName = ucfirst( $network ) . ' Profile';

		if ( $network === 'facebook' ) {
			$url      = "https://graph.facebook.com/v19.0/oauth/access_token?client_id={$appId}&redirect_uri=" . urlencode( $callbackUri ) . "&client_secret={$appSecret}&code={$code}";
			$response = wp_safe_remote_get( $url );
			if ( ! is_wp_error( $response ) ) {
				$body        = json_decode( wp_remote_retrieve_body( $response ), true );
				$accessToken = $body['access_token'] ?? '';

				if ( $accessToken ) {
					$pagesRes = wp_safe_remote_get( "https://graph.facebook.com/v19.0/me/accounts?access_token={$accessToken}" );
					if ( ! is_wp_error( $pagesRes ) ) {
						$pagesBody = json_decode( wp_remote_retrieve_body( $pagesRes ), true );
						if ( ! empty( $pagesBody['data'][0] ) ) {
							$page        = $pagesBody['data'][0];
							$accessToken = $page['access_token'];
							$profileName = $page['name'];
						}
					}
				}
			}
		} else {
			$tokenEndpoints = array(
				'linkedin'  => 'https://www.linkedin.com/oauth/v2/accessToken',
				'pinterest' => 'https://api.pinterest.com/v5/oauth/token',
				'twitter'   => 'https://api.twitter.com/2/oauth2/token',
			);

			if ( isset( $tokenEndpoints[ $network ] ) ) {
				$args = array(
					'body' => array(
						'grant_type'    => 'authorization_code',
						'code'          => $code,
						'redirect_uri'  => $callbackUri,
						'client_id'     => $appId,
						'client_secret' => $appSecret,
					),
				);

				if ( $network === 'twitter' ) {
					$args['headers'] = array( 'Authorization' => 'Basic ' . base64_encode( "$appId:$appSecret" ) );
				}

				$response = wp_safe_remote_post( $tokenEndpoints[ $network ], $args );
				if ( ! is_wp_error( $response ) ) {
					$body        = json_decode( wp_remote_retrieve_body( $response ), true );
					$accessToken = $body['access_token'] ?? '';
				}
			}
		}

		if ( $accessToken ) {
			$manager = new SocialAccountManager();
			$manager->saveAccount(
				array(
					'network'      => sanitize_text_field( $network ),
					'access_token' => $accessToken,
					'profile_name' => $profileName,
					'auto_share'   => true,
					'created_at'   => time(),
				)
			);
		}

		wp_redirect( $redirectUrl );
		exit;
	}

	public function getSocialSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$apps = get_option( 'ameverywhere_social_apps', array() );

		// Mask secrets before sending to frontend
		foreach ( $apps as $network => $creds ) {
			if ( ! empty( $creds['app_secret'] ) ) {
				$apps[ $network ]['app_secret'] = '********';
			}
		}

		return rest_ensure_response(
			array(
				'apps' => $apps,
			)
		);
	}

	public function saveSocialSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$apps   = get_option( 'ameverywhere_social_apps', array() );

		$networks = array( 'facebook', 'twitter', 'linkedin', 'pinterest' );

		foreach ( $networks as $net ) {
			if ( isset( $params[ $net ] ) ) {
				$creds      = $params[ $net ];
				$app_id     = sanitize_text_field( $creds['app_id'] ?? '' );
				$app_secret = sanitize_text_field( $creds['app_secret'] ?? '' );

				// Only update secret if it's not the masked value
				if ( $app_secret === '********' ) {
					$app_secret = $apps[ $net ]['app_secret'] ?? '';
				} elseif ( ! empty( $app_secret ) ) {
					$app_secret = KeyVault::encrypt( $app_secret );
				}

				$apps[ $net ] = array(
					'app_id'     => $app_id,
					'app_secret' => $app_secret,
				);
			}
		}

		update_option( 'ameverywhere_social_apps', $apps );

		// Mask before returning
		foreach ( $apps as $network => $creds ) {
			if ( ! empty( $creds['app_secret'] ) ) {
				$apps[ $network ]['app_secret'] = '********';
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'apps'    => $apps,
			)
		);
	}
}
