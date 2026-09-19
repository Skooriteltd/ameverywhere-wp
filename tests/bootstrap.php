<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', dirname(__DIR__) . '/wp-content');
}
if (!defined('AMEVERYWHERE_VERSION')) {
    define('AMEVERYWHERE_VERSION', '1.0.0');
}
if (!defined('SECURE_AUTH_KEY')) {
    define('SECURE_AUTH_KEY', 'test_secure_auth_key_1234567890abcdef');
}

// In-memory WordPress options store for unit tests
global $mock_wp_options;
$mock_wp_options = [];

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        global $mock_wp_options;
        return $mock_wp_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value, $autoload = null): bool {
        global $mock_wp_options;
        $mock_wp_options[$option] = $value;
        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option(string $option, $value = '', $deprecated = '', $autoload = 'yes'): bool {
        global $mock_wp_options;
        if (isset($mock_wp_options[$option])) {
            return false;
        }
        $mock_wp_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool {
        global $mock_wp_options;
        unset($mock_wp_options[$option]);
        return true;
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'auth'): string {
        return 'test_mock_salt_value_for_unit_tests_' . $scheme;
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string {
        return strip_tags(trim($str));
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string {
        return $text;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public string $code;
        public string $message;
        public $data;

        public function __construct(string $code = '', string $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return ($thing instanceof WP_Error);
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit(string $string): string {
        return rtrim($string, '/\\');
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1) {
        return parse_url($url, $component);
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($original) {
        return $original;
    }
}

if (!isset($GLOBALS['wpdb'])) {
    class MockWpdb {
        public string $prefix = 'wp_';
        public string $options = 'wp_options';
        public string $postmeta = 'wp_postmeta';
        public array $queries = [];

        public function get_results(string $query = null, $output = 'OBJECT') {
            $this->queries[] = $query;
            return [];
        }

        public function query(string $query) {
            $this->queries[] = $query;
            return true;
        }

        public function get_var($query = null, $x = 0, $y = 0) {
            $this->queries[] = $query;
            return null;
        }

        public function prepare(string $query, ...$args): string {
            return vsprintf(str_replace('%s', "'%s'", $query), $args);
        }

        public function esc_like(string $text): string {
            return addcslashes($text, '_%\\');
        }

        public function get_charset_collate(): string {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
    }
    $GLOBALS['wpdb'] = new MockWpdb();
}

// Post & Meta store
global $mock_post_meta, $mock_posts, $mock_current_post_id;
$mock_post_meta = [];
$mock_posts = [];
$mock_current_post_id = 0;

// Conditional tags state
global $mock_is_singular, $mock_is_admin, $mock_is_front_page, $mock_is_home, $mock_is_404, $mock_is_search, $mock_is_paged, $mock_is_date;
$mock_is_singular = false;
$mock_is_admin = false;
$mock_is_front_page = false;
$mock_is_home = false;
$mock_is_404 = false;
$mock_is_search = false;
$mock_is_paged = false;
$mock_is_date = false;

if (!class_exists('WP_Post')) {
    class WP_Post {
        public int $ID = 0;
        public string $post_title = '';
        public string $post_content = '';
        public string $post_excerpt = '';
        public string $post_type = 'post';
        public string $post_status = 'publish';
        public int $post_author = 1;
        public string $post_date = '2026-01-01 12:00:00';
        public string $post_modified = '2026-01-02 12:00:00';

        public function __construct(array $data = []) {
            foreach ($data as $key => $val) {
                $this->$key = $val;
            }
        }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        public const READABLE = 'GET';
        public const CREATABLE = 'POST';
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private array $params = [];
        private array $jsonParams = [];

        public function __construct(string $method = 'GET', string $route = '') {}

        public function set_json_params(array $params): void {
            $this->jsonParams = $params;
        }

        public function get_json_params(): array {
            return $this->jsonParams;
        }

        public function set_param(string $key, $value): void {
            $this->params[$key] = $value;
        }

        public function get_param(string $key) {
            return $this->params[$key] ?? null;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public int $status;

        public function __construct($data = null, int $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status(): int {
            return $this->status;
        }
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($response) {
        if ($response instanceof WP_REST_Response) {
            return $response;
        }
        return new WP_REST_Response($response);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, ...$args): bool {
        return true;
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $str): string {
        return strip_tags(trim($str));
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name(string $filename): string {
        return preg_replace('/[^a-zA-Z0-9_\.-]/', '', $filename);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        global $mock_is_admin;
        return (bool) $mock_is_admin;
    }
}

if (!function_exists('is_singular')) {
    function is_singular($post_types = ''): bool {
        global $mock_is_singular;
        return (bool) $mock_is_singular;
    }
}

if (!function_exists('is_single')) {
    function is_single($post = ''): bool {
        global $mock_is_singular;
        return (bool) $mock_is_singular;
    }
}

if (!function_exists('is_front_page')) {
    function is_front_page(): bool {
        global $mock_is_front_page;
        return (bool) $mock_is_front_page;
    }
}

if (!function_exists('is_home')) {
    function is_home(): bool {
        global $mock_is_home;
        return (bool) $mock_is_home;
    }
}

if (!function_exists('is_404')) {
    function is_404(): bool {
        global $mock_is_404;
        return (bool) $mock_is_404;
    }
}

if (!function_exists('is_search')) {
    function is_search(): bool {
        global $mock_is_search;
        return (bool) $mock_is_search;
    }
}

if (!function_exists('is_paged')) {
    function is_paged(): bool {
        global $mock_is_paged;
        return (bool) $mock_is_paged;
    }
}

if (!function_exists('is_date')) {
    function is_date(): bool {
        global $mock_is_date;
        return (bool) $mock_is_date;
    }
}

if (!function_exists('is_day')) {
    function is_day(): bool {
        return false;
    }
}

if (!function_exists('is_month')) {
    function is_month(): bool {
        return false;
    }
}

if (!function_exists('is_category')) {
    function is_category(): bool {
        return false;
    }
}

if (!function_exists('is_tag')) {
    function is_tag(): bool {
        return false;
    }
}

if (!function_exists('is_tax')) {
    function is_tax(): bool {
        return false;
    }
}

if (!function_exists('is_author')) {
    function is_author(): bool {
        return false;
    }
}

if (!function_exists('is_post_type_archive')) {
    function is_post_type_archive(): bool {
        return false;
    }
}

if (!function_exists('get_the_ID')) {
    function get_the_ID(): int {
        global $mock_current_post_id;
        return (int) $mock_current_post_id;
    }
}

if (!function_exists('get_post')) {
    function get_post($post = null) {
        global $mock_posts, $mock_current_post_id;
        if ($post === null || $post === 0) {
            $post = $mock_current_post_id;
        }
        if (is_numeric($post)) {
            return $mock_posts[(int) $post] ?? null;
        }
        if ($post instanceof WP_Post) {
            return $post;
        }
        return null;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta(int $post_id, string $key = '', bool $single = false) {
        global $mock_post_meta;
        if (empty($key)) {
            return $mock_post_meta[$post_id] ?? [];
        }
        return $mock_post_meta[$post_id][$key] ?? ($single ? '' : []);
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta(int $post_id, string $key, $value, $prev_value = ''): bool {
        global $mock_post_meta;
        if (!isset($mock_post_meta[$post_id])) {
            $mock_post_meta[$post_id] = [];
        }
        $mock_post_meta[$post_id][$key] = $value;
        return true;
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post = 0): string {
        $p = get_post($post);
        return $p ? $p->post_title : 'Test Title';
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = 'name'): string {
        if ($show === 'name') {
            return 'AmEveryWhere Test Site';
        }
        if ($show === 'description') {
            return 'AI Powered SEO Plugin';
        }
        return '';
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post = 0): string {
        $id = is_numeric($post) && $post > 0 ? (int) $post : get_the_ID();
        return "https://example.com/post-{$id}/";
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = '', ?string $scheme = null): string {
        return 'https://example.com' . (str_starts_with($path, '/') ? $path : '/' . $path);
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $string, bool $remove_breaks = false): string {
        return strip_tags($string);
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words(string $text, int $num_words = 55, ?string $more = null): string {
        $words = preg_split('/\s+/', trim($text));
        if (count($words) <= $num_words) {
            return trim($text);
        }
        return implode(' ', array_slice($words, 0, $num_words)) . ($more ?? '…');
    }
}

if (!function_exists('get_post_field')) {
    function get_post_field(string $field, $post = 0): string {
        $p = get_post($post);
        return $p && isset($p->$field) ? (string) $p->$field : '';
    }
}

if (!function_exists('get_the_date')) {
    function get_the_date(string $format = '', $post = 0): string {
        return '2026-01-01T12:00:00+00:00';
    }
}

if (!function_exists('get_the_modified_date')) {
    function get_the_modified_date(string $format = '', $post = 0): string {
        return '2026-01-02T12:00:00+00:00';
    }
}

if (!function_exists('get_the_author_meta')) {
    function get_the_author_meta(string $field = '', $user_id = false): string {
        if ($field === 'display_name') return 'John Doe';
        if ($field === 'description') return 'Author bio';
        return '';
    }
}

if (!function_exists('get_author_posts_url')) {
    function get_author_posts_url(int $author_id): string {
        return "https://example.com/author/{$author_id}/";
    }
}

if (!function_exists('get_site_icon_url')) {
    function get_site_icon_url(int $size = 512, string $url = '', int $blog_id = 0): string {
        return 'https://example.com/icon.png';
    }
}

if (!function_exists('has_post_thumbnail')) {
    function has_post_thumbnail($post = null): bool {
        return false;
    }
}

if (!function_exists('get_the_post_thumbnail_url')) {
    function get_the_post_thumbnail_url($post = null, string|array $size = 'post-thumbnail'): string {
        return '';
    }
}

if (!function_exists('get_the_category')) {
    function get_the_category($post_id = false): array {
        return [];
    }
}

if (!function_exists('get_category_link')) {
    function get_category_link($category): string {
        return 'https://example.com/category/tech/';
    }
}

if (!function_exists('wp_get_post_categories')) {
    function wp_get_post_categories(int $post_id = 0, array $args = []): array {
        return [];
    }
}

if (!function_exists('get_the_excerpt')) {
    function get_the_excerpt($post = 0): string {
        $p = get_post($post);
        return $p ? $p->post_excerpt : '';
    }
}

if (!function_exists('get_post_modified_time')) {
    function get_post_modified_time(string $format = 'U', bool $gmt = false, $post = null, bool $translate = false) {
        return '2026-01-02T12:00:00+00:00';
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0, int $depth = 512): string {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true {
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true {
        return true;
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args = [], bool $override = false): bool {
        return true;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args): string {
        return 'https://example.com/';
    }
}

if (!function_exists('absint')) {
    function absint($maybeint): int {
        return abs((int) $maybeint);
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool {
        return @mkdir($target, 0777, true);
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = '', ?string $scheme = null): string {
        return 'https://example.com' . (str_starts_with($path, '/') ? $path : '/' . $path);
    }
}

if (!isset($GLOBALS['wp'])) {
    $wpObj = new \stdClass();
    $wpObj->request = '';
    $GLOBALS['wp'] = $wpObj;
}

if (!function_exists('remove_accents')) {
    function remove_accents(string $string, string $locale = ''): string {
        $chars = [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ç' => 'c',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y', 'Ñ' => 'N', 'Ç' => 'C',
        ];
        return strtr($string, $chars);
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string {
        return trailingslashit(dirname($file));
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string {
        return 'https://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $string): string {
        return rtrim($string, '/\\') . '/';
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, callable|array $callback): void {}
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, callable|array $callback): void {}
}



