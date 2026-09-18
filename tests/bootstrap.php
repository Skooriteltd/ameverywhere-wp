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
            global $mock_wp_options;
            $this->queries[] = $query;
            if (str_contains($query, 'ranksavvy_%')) {
                $results = [];
                foreach ($mock_wp_options as $k => $v) {
                    if (str_starts_with($k, 'ranksavvy_')) {
                        $obj = new \stdClass();
                        $obj->option_name = $k;
                        $obj->option_value = $v;
                        $obj->autoload = 'yes';
                        $results[] = $obj;
                    }
                }
                return $results;
            }
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

        public function get_charset_collate(): string {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
    }
    $GLOBALS['wpdb'] = new MockWpdb();
}
