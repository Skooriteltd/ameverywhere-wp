<?php

namespace RankSavvy\Modules\TechnicalSeo;

class RedirectManager
{
    public function handleRedirects(): void
    {
        if (is_admin()) {
            return;
        }

        $currentUrl = $this->getCurrentUrl();
        
        // MVP: In a full implementation, we would query the database for configured redirects.
        // For MVP structure, we simulate finding a redirect rule.
        $redirects = $this->getRedirectRules();

        foreach ($redirects as $rule) {
            // Simple exact match logic
            if (untrailingslashit($currentUrl) === untrailingslashit($rule['source'])) {
                wp_redirect($rule['target'], $rule['code']);
                exit;
            }

            // Regex match logic
            if ($rule['is_regex'] && preg_match('#' . $rule['source'] . '#i', $currentUrl)) {
                $target = preg_replace('#' . $rule['source'] . '#i', $rule['target'], $currentUrl);
                wp_redirect($target, $rule['code']);
                exit;
            }
        }
    }

    private function getCurrentUrl(): string
    {
        global $wp;
        return home_url(add_query_arg([], $wp->request));
    }

    private function getRedirectRules(): array
    {
        // Retrieve from custom table or options.
        return get_option('ranksavvy_redirects', []);
    }
}
