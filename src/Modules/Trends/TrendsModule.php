<?php

namespace AmEveryWhere\Modules\Trends;

use AmEveryWhere\Core\Event\EventManager;

class TrendsModule
{
    private EventManager $eventManager;

    public function __construct(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;
    }

    public function boot(): void
    {
        $this->eventManager->addAction('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('ameverywhere/v1', '/trends', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getTrends'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => [
                'keyword' => ['required' => true, 'type' => 'string'],
                'geo'     => ['required' => false, 'type' => 'string', 'default' => '']
            ]
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('edit_posts');
    }

    public function getTrends(\WP_REST_Request $request): \WP_REST_Response
    {
        $keyword = sanitize_text_field($request->get_param('keyword'));
        $geo = sanitize_text_field($request->get_param('geo'));

        $client = new GoogleTrendsClient();
        $data = $client->fetchTrendData($keyword, $geo);

        if (isset($data['error'])) {
            return new \WP_REST_Response(['success' => false, 'message' => $data['error']], 500);
        }

        return rest_ensure_response(['success' => true, 'data' => $data]);
    }
}
