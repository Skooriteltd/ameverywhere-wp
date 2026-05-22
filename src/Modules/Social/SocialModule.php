<?php

namespace RankSavvy\Modules\Social;

use RankSavvy\Core\Event\EventManager;
use RankSavvy\Core\Queue\QueueManager;
use RankSavvy\Core\Security\KeyVault;

class SocialModule
{
    private EventManager $eventManager;

    public function __construct(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;
    }

    public function boot(): void
    {
        $this->eventManager->addAction('rest_api_init', [$this, 'registerRestRoutes']);
        $this->eventManager->addAction('transition_post_status', [$this, 'handlePostTransition'], 10, 3);
    }

    public function handlePostTransition($newStatus, $oldStatus, $post): void
    {
        // Only trigger on first publish
        if ($newStatus === 'publish' && $oldStatus !== 'publish') {
            
            // Check global auto-share toggle setting
            $globalAutoShare = get_option('ranksavvy_global_auto_share', 'yes');
            if ($globalAutoShare !== 'yes') {
                return;
            }

            // Dispatch job
            $queueManager = new QueueManager();
            $queueManager->push(AutoShareJob::class, [
                'post_id' => $post->ID,
                'retries' => 0,
                'manual'  => false
            ]);
        }
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('ranksavvy/v1', '/social/accounts', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getAccounts'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'saveAccount'],
                'permission_callback' => [$this, 'checkPermission'],
            ]
        ]);

        register_rest_route('ranksavvy/v1', '/social/accounts/(?P<id>[\w-]+)', [
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteAccount'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE, // PUT/PATCH
                'callback'            => [$this, 'updateAccount'],
                'permission_callback' => [$this, 'checkPermission'],
            ]
        ]);

        register_rest_route('ranksavvy/v1', '/social/share', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'manualShare'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Mock OAuth callback endpoints for testing UI
        register_rest_route('ranksavvy/v1', '/social/oauth-callback', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'oauthCallback'],
            'permission_callback' => '__return_true', // Public for redirect
        ]);

        register_rest_route('ranksavvy/v1', '/social/settings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSocialSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'saveSocialSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ]
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options') || current_user_can('edit_posts');
    }

    public function getAccounts(\WP_REST_Request $request): \WP_REST_Response
    {
        $manager = new SocialAccountManager();
        $accounts = $manager->getAccounts();

        // Mask access tokens
        foreach ($accounts as &$acc) {
            if (isset($acc['access_token'])) {
                $acc['access_token'] = '********';
            }
        }
        unset($acc);

        $cats = get_categories(['hide_empty' => false]);
        $categories = array_map(function ($cat) {
            return ['id' => $cat->term_id, 'name' => $cat->name];
        }, $cats);

        return rest_ensure_response([
            'accounts' => $accounts,
            'global_auto_share' => get_option('ranksavvy_global_auto_share', 'yes') === 'yes',
            'categories' => $categories
        ]);
    }

    public function saveAccount(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        if (isset($params['global_auto_share'])) {
            update_option('ranksavvy_global_auto_share', $params['global_auto_share'] ? 'yes' : 'no');
            return rest_ensure_response(['success' => true]);
        }

        if (empty($params['network']) || empty($params['access_token'])) {
            return new \WP_Error('missing_params', 'Network and Access Token are required.', ['status' => 400]);
        }

        $manager = new SocialAccountManager();
        $manager->saveAccount([
            'network'      => sanitize_text_field($params['network']),
            'access_token' => sanitize_text_field($params['access_token']),
            'profile_name' => sanitize_text_field($params['profile_name'] ?? 'Unknown Profile'),
            'auto_share'   => isset($params['auto_share']) ? (bool) $params['auto_share'] : true,
            'created_at'   => time()
        ]);

        return rest_ensure_response(['success' => true, 'accounts' => $manager->getAccounts()]);
    }

    public function deleteAccount(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $manager = new SocialAccountManager();
        
        if ($manager->deleteAccount($id)) {
            return rest_ensure_response(['success' => true, 'accounts' => $manager->getAccounts()]);
        }

        return new \WP_Error('not_found', 'Account not found.', ['status' => 404]);
    }

    public function updateAccount(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $params = $request->get_json_params();
        
        $manager = new SocialAccountManager();
        if ($manager->updateAccount($id, $params)) {
            return rest_ensure_response(['success' => true, 'accounts' => $manager->getAccounts()]);
        }

        return new \WP_Error('not_found', 'Account not found.', ['status' => 404]);
    }

    public function manualShare(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = $request->get_param('post_id');
        if (!$postId) {
            return new \WP_Error('missing_param', 'Post ID is required.', ['status' => 400]);
        }

        // We dispatch immediately via job queue (but we could also run it synchronously for immediate UI feedback, 
        // since the user expects a quick 'success'. However, queue is safer. Let's just run it inline for manual trigger UI responsiveness).
        
        $job = new AutoShareJob([
            'post_id' => $postId,
            'retries' => 0,
            'manual'  => true
        ]);
        $job->handle();

        return rest_ensure_response(['success' => true, 'message' => 'Share dispatched successfully.']);
    }

    public function oauthCallback(\WP_REST_Request $request): \WP_REST_Response
    {
        $network = $request->get_param('network');
        $code = $request->get_param('code');
        $error = $request->get_param('error');
        
        $redirectUrl = admin_url('admin.php?page=ranksavvy#/social');

        if ($error || !$code) {
            wp_redirect($redirectUrl);
            exit;
        }

        $apps = get_option('ranksavvy_social_apps', []);
        if (empty($apps[$network]['app_id']) || empty($apps[$network]['app_secret'])) {
            wp_redirect($redirectUrl);
            exit;
        }

        $appId = $apps[$network]['app_id'];
        $appSecret = KeyVault::decrypt($apps[$network]['app_secret']);
        $callbackUri = rest_url("ranksavvy/v1/social/oauth-callback?network={$network}");
        
        $accessToken = '';
        $profileName = ucfirst($network) . ' Profile';

        if ($network === 'facebook') {
            $url = "https://graph.facebook.com/v19.0/oauth/access_token?client_id={$appId}&redirect_uri=" . urlencode($callbackUri) . "&client_secret={$appSecret}&code={$code}";
            $response = wp_remote_get($url);
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $accessToken = $body['access_token'] ?? '';
                
                if ($accessToken) {
                    $pagesRes = wp_remote_get("https://graph.facebook.com/v19.0/me/accounts?access_token={$accessToken}");
                    if (!is_wp_error($pagesRes)) {
                        $pagesBody = json_decode(wp_remote_retrieve_body($pagesRes), true);
                        if (!empty($pagesBody['data'][0])) {
                            $page = $pagesBody['data'][0];
                            $accessToken = $page['access_token'];
                            $profileName = $page['name'];
                        }
                    }
                }
            }
        } else {
            $tokenEndpoints = [
                'linkedin' => 'https://www.linkedin.com/oauth/v2/accessToken',
                'pinterest' => 'https://api.pinterest.com/v5/oauth/token',
                'twitter' => 'https://api.twitter.com/2/oauth2/token',
            ];
            
            if (isset($tokenEndpoints[$network])) {
                $args = [
                    'body' => [
                        'grant_type' => 'authorization_code',
                        'code' => $code,
                        'redirect_uri' => $callbackUri,
                        'client_id' => $appId,
                        'client_secret' => $appSecret
                    ]
                ];
                
                if ($network === 'twitter') {
                    $args['headers'] = ['Authorization' => 'Basic ' . base64_encode("$appId:$appSecret")];
                }

                $response = wp_remote_post($tokenEndpoints[$network], $args);
                if (!is_wp_error($response)) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    $accessToken = $body['access_token'] ?? '';
                }
            }
        }

        if ($accessToken) {
            $manager = new SocialAccountManager();
            $manager->saveAccount([
                'network'      => sanitize_text_field($network),
                'access_token' => $accessToken,
                'profile_name' => $profileName,
                'auto_share'   => true,
                'created_at'   => time()
            ]);
        }

        wp_redirect($redirectUrl);
        exit;
    }

    public function getSocialSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $apps = get_option('ranksavvy_social_apps', []);
        
        // Mask secrets before sending to frontend
        foreach ($apps as $network => $creds) {
            if (!empty($creds['app_secret'])) {
                $apps[$network]['app_secret'] = '********';
            }
        }

        return rest_ensure_response([
            'apps' => $apps
        ]);
    }

    public function saveSocialSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $apps = get_option('ranksavvy_social_apps', []);

        $networks = ['facebook', 'twitter', 'linkedin', 'pinterest'];

        foreach ($networks as $net) {
            if (isset($params[$net])) {
                $creds = $params[$net];
                $app_id = sanitize_text_field($creds['app_id'] ?? '');
                $app_secret = sanitize_text_field($creds['app_secret'] ?? '');

                // Only update secret if it's not the masked value
                if ($app_secret === '********') {
                    $app_secret = $apps[$net]['app_secret'] ?? '';
                } elseif (!empty($app_secret)) {
                    $app_secret = KeyVault::encrypt($app_secret);
                }

                $apps[$net] = [
                    'app_id' => $app_id,
                    'app_secret' => $app_secret
                ];
            }
        }

        update_option('ranksavvy_social_apps', $apps);

        // Mask before returning
        foreach ($apps as $network => $creds) {
            if (!empty($creds['app_secret'])) {
                $apps[$network]['app_secret'] = '********';
            }
        }

        return rest_ensure_response(['success' => true, 'apps' => $apps]);
    }
}
