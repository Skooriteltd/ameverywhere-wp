<?php

namespace RankSavvy\Modules\Social;

use RankSavvy\Core\Security\KeyVault;

class SocialApiGateway
{
    /**
     * Dispatch the post to the specified network using the account details.
     */
    public function share(array $account, array $payload): array
    {
        $network = $account['network'] ?? 'unknown';
        $encryptedToken = $account['access_token'] ?? '';

        if (empty($encryptedToken)) {
            return [
                'success' => false,
                'message' => 'No access token available for this account.',
                'code' => 401
            ];
        }

        // Decrypt the token before making the call
        $token = KeyVault::decrypt($encryptedToken);
        if (empty($token)) {
             return [
                'success' => false,
                'message' => 'Failed to decrypt access token.',
                'code' => 401
            ];
        }

        // Validate payload content
        if (empty($payload['url'])) {
            return [
                'success' => false,
                'message' => 'Cannot share without a URL.',
                'code' => 400
            ];
        }

        $message = sanitize_textarea_field($payload['message'] ?? '');
        $url = esc_url_raw($payload['url']);

        switch ($network) {
            case 'facebook':
                return $this->shareFacebook($token, $message, $url);
            case 'linkedin':
                return $this->shareLinkedIn($token, $message, $url);
            case 'twitter':
                return $this->shareTwitter($token, $message, $url);
            case 'pinterest':
                return $this->sharePinterest($token, $message, $url);
            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported network.',
                    'code' => 400
                ];
        }
    }

    private function shareFacebook(string $token, string $message, string $url): array
    {
        // Facebook Graph API v19.0 endpoint for Page Feed
        $apiUrl = 'https://graph.facebook.com/v19.0/me/feed';
        
        $body = [
            'message' => $message,
            'link' => $url,
            'access_token' => $token
        ];

        $response = wp_remote_post($apiUrl, [
            'body' => $body
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message(), 'code' => 500];
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = json_decode(wp_remote_retrieve_body($response), true);

        if ($responseCode >= 200 && $responseCode < 300) {
            return ['success' => true, 'post_id' => $responseBody['id'] ?? '', 'code' => $responseCode];
        }

        return [
            'success' => false,
            'message' => $responseBody['error']['message'] ?? 'Unknown Facebook error',
            'code' => $responseCode
        ];
    }

    private function shareTwitter(string $token, string $message, string $url): array
    {
        // Twitter API v2
        $apiUrl = 'https://api.twitter.com/2/tweets';
        
        $body = [
            'text' => $message . "\n" . $url
        ];

        $response = wp_remote_post($apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body)
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message(), 'code' => 500];
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = json_decode(wp_remote_retrieve_body($response), true);

        if ($responseCode >= 200 && $responseCode < 300) {
            return ['success' => true, 'post_id' => $responseBody['data']['id'] ?? '', 'code' => $responseCode];
        }

        return [
            'success' => false,
            'message' => $responseBody['detail'] ?? 'Unknown Twitter error',
            'code' => $responseCode
        ];
    }

    private function shareLinkedIn(string $token, string $message, string $url): array
    {
        $meResponse = wp_remote_get('https://api.linkedin.com/v2/userinfo', [
            'headers' => ['Authorization' => 'Bearer ' . $token]
        ]);
        
        if (is_wp_error($meResponse)) {
             return ['success' => false, 'message' => 'Failed to fetch LinkedIn profile.', 'code' => 500];
        }
        $meBody = json_decode(wp_remote_retrieve_body($meResponse), true);
        $urn = $meBody['sub'] ?? '';
        
        if (!$urn) {
             return ['success' => false, 'message' => 'Could not determine LinkedIn author URN.', 'code' => 400];
        }

        $apiUrl = 'https://api.linkedin.com/v2/ugcPosts';
        
        $body = [
            'author' => 'urn:li:person:' . $urn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $message],
                    'shareMediaCategory' => 'ARTICLE',
                    'media' => [
                        [
                            'status' => 'READY',
                            'originalUrl' => $url
                        ]
                    ]
                ]
            ],
            'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC']
        ];

        $response = wp_remote_post($apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0'
            ],
            'body' => wp_json_encode($body)
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message(), 'code' => 500];
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        if ($responseCode >= 200 && $responseCode < 300) {
            $headers = wp_remote_retrieve_headers($response);
            $postId = $headers['x-linkedin-id'] ?? '';
            return ['success' => true, 'post_id' => $postId, 'code' => $responseCode];
        }
        
        $responseBody = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'success' => false,
            'message' => $responseBody['message'] ?? 'Unknown LinkedIn error',
            'code' => $responseCode
        ];
    }

    private function sharePinterest(string $token, string $message, string $url): array
    {
        $boardsRes = wp_remote_get('https://api.pinterest.com/v5/boards', [
             'headers' => ['Authorization' => 'Bearer ' . $token]
        ]);
        if (is_wp_error($boardsRes)) {
            return ['success' => false, 'message' => 'Failed to fetch Pinterest boards.', 'code' => 500];
        }
        
        $boardsBody = json_decode(wp_remote_retrieve_body($boardsRes), true);
        $boardId = $boardsBody['items'][0]['id'] ?? '';
        
        if (!$boardId) {
             return ['success' => false, 'message' => 'No Pinterest boards found to pin to.', 'code' => 400];
        }
        
        $apiUrl = 'https://api.pinterest.com/v5/pins';
        $body = [
            'board_id' => $boardId,
            'title' => substr($message, 0, 100),
            'description' => $message,
            'link' => $url
        ];

        $response = wp_remote_post($apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body)
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message(), 'code' => 500];
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = json_decode(wp_remote_retrieve_body($response), true);

        if ($responseCode >= 200 && $responseCode < 300) {
            return ['success' => true, 'post_id' => $responseBody['id'] ?? '', 'code' => $responseCode];
        }

        return [
            'success' => false,
            'message' => $responseBody['message'] ?? 'Unknown Pinterest error',
            'code' => $responseCode
        ];
    }
}
