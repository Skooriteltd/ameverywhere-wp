<?php

namespace RankSavvy\Modules\TechnicalSeo;

class ErrorMonitor
{
    public function log404Errors(): void
    {
        if (!is_404()) {
            return;
        }

        // Avoid logging assets like images or CSS maps
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (preg_match('/\.(jpg|jpeg|png|gif|ico|css|js|map)$/i', $uri)) {
            return;
        }

        $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        // MVP: Log to a database table or transient for the admin dashboard to display.
        // Example: $wpdb->insert(...)
        
        // This log data will power the "Broken Link Monitor" widget in the Publisher Dashboard.
    }
}
