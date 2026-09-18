<?php

namespace AmEveryWhere\Modules\Admin;

/**
 * FrontendSeoInspector
 *
 * Injects a floating SEO audit overlay for logged-in editors.
 * Operates in Shadow DOM isolation to prevent styling conflicts.
 *
 * BL-019
 */
class FrontendSeoInspector
{
    public function register(): void
    {
        add_action('wp_footer', [$this, 'injectOverlay'], 99);
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('ameverywhere/v1', '/inspector/audit', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'runAudit'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    public function runAudit(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $postId = absint($params['post_id'] ?? 0);

        if (!$postId) {
            return new \WP_Error('missing_post_id', 'post_id is required.', ['status' => 400]);
        }

        return rest_ensure_response($this->auditPost($postId));
    }

    public function auditPost(int $postId): array
    {
        $post    = get_post($postId);
        $results = [];

        $metaTitle    = (string) get_post_meta($postId, '_ameverywhere_meta_title', true);
        $metaDesc     = (string) get_post_meta($postId, '_ameverywhere_meta_description', true);
        $focusKw      = (string) get_post_meta($postId, '_ameverywhere_focus_keyword', true);
        $canonical    = (string) get_post_meta($postId, '_ameverywhere_canonical_url', true);
        $noindex      = get_post_meta($postId, '_ameverywhere_noindex', true) === 'yes';
        $thumbnail    = has_post_thumbnail($postId);
        $content      = wp_strip_all_tags($post->post_content ?? '');
        $wordCount    = str_word_count($content);

        // Meta title
        $titleLen = mb_strlen($metaTitle);
        $results['meta_title'] = [
            'status'  => empty($metaTitle) ? 'fail' : ($titleLen < 10 || $titleLen > 70 ? 'warn' : 'pass'),
            'value'   => $metaTitle ?: 'Not set',
            'message' => empty($metaTitle) ? 'Meta title is missing.' : ($titleLen > 70 ? 'Title exceeds 70 characters.' : 'OK'),
        ];

        // Meta description
        $descLen = mb_strlen($metaDesc);
        $results['meta_description'] = [
            'status'  => empty($metaDesc) ? 'fail' : ($descLen < 50 || $descLen > 165 ? 'warn' : 'pass'),
            'value'   => $metaDesc ?: 'Not set',
            'message' => empty($metaDesc) ? 'Meta description is missing.' : ($descLen > 165 ? 'Description exceeds 165 characters.' : 'OK'),
        ];

        // Focus keyword
        $results['focus_keyword'] = [
            'status'  => empty($focusKw) ? 'warn' : 'pass',
            'value'   => $focusKw ?: 'Not set',
            'message' => empty($focusKw) ? 'No focus keyword set.' : 'OK',
        ];

        // Canonical URL
        $results['canonical'] = [
            'status'  => empty($canonical) ? 'warn' : 'pass',
            'value'   => $canonical ?: get_permalink($postId),
            'message' => 'OK',
        ];

        // Robots — noindex should not be set unless intentional
        $results['robots'] = [
            'status'  => $noindex ? 'warn' : 'pass',
            'value'   => $noindex ? 'noindex' : 'index',
            'message' => $noindex ? 'Post is set to noindex. Verify this is intentional.' : 'OK',
        ];

        // Featured image
        $results['featured_image'] = [
            'status'  => $thumbnail ? 'pass' : 'warn',
            'value'   => $thumbnail ? 'Present' : 'Missing',
            'message' => $thumbnail ? 'OK' : 'No featured image. Add one for OG/Twitter cards.',
        ];

        // Word count
        $results['word_count'] = [
            'status'  => $wordCount < 300 ? 'warn' : 'pass',
            'value'   => $wordCount,
            'message' => $wordCount < 300 ? 'Content is too thin (under 300 words).' : 'OK',
        ];

        // Summary
        $pass  = count(array_filter($results, fn($r) => $r['status'] === 'pass'));
        $warns = count(array_filter($results, fn($r) => $r['status'] === 'warn'));
        $fails = count(array_filter($results, fn($r) => $r['status'] === 'fail'));

        return [
            'post_id' => $postId,
            'checks'  => $results,
            'summary' => ['pass' => $pass, 'warn' => $warns, 'fail' => $fails, 'score' => (int) round(($pass / count($results)) * 100)],
        ];
    }

    /**
     * Inject floating inspector overlay (Shadow DOM isolated, editor-only).
     */
    public function injectOverlay(): void
    {
        if (!is_singular() || !current_user_can('edit_posts')) {
            return;
        }

        $postId  = get_the_ID();
        $apiBase = esc_url(rest_url('ameverywhere/v1'));
        $nonce   = wp_create_nonce('wp_rest');
        ?>
        <div id="ranksavvy-inspector-host" style="position:fixed;bottom:20px;right:20px;z-index:2147483647;font-family:sans-serif"></div>
        <script>
        (function(){
          'use strict';
          const host = document.getElementById('ranksavvy-inspector-host');
          if (!host || !host.attachShadow) return;
          const shadow = host.attachShadow({mode:'open'});

          const style = `
            :host { all: initial; }
            #toggle { width:48px;height:48px;border-radius:50%;background:#2563eb;color:#fff;border:none;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.25); }
            #panel { display:none;position:absolute;bottom:58px;right:0;width:340px;max-height:70vh;overflow-y:auto;background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.18);padding:16px; }
            #panel.open { display:block; }
            h3 { margin:0 0 12px;font-size:14px;font-weight:700;color:#1e293b; }
            .row { display:flex;justify-content:space-between;align-items:flex-start;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:12px; }
            .label { color:#64748b;flex:1; }
            .badge { padding:2px 8px;border-radius:99px;font-weight:600;font-size:11px; }
            .pass { background:#dcfce7;color:#15803d; }
            .warn { background:#fef9c3;color:#854d0e; }
            .fail { background:#fee2e2;color:#b91c1c; }
            .score { font-size:28px;font-weight:800;margin:0 0 12px;color:#2563eb; }
          `;

          shadow.innerHTML = `<style>${style}</style>
            <div id="panel">
              <h3>🔍 AmEveryWhere Inspector</h3>
              <div class="score" id="score">–</div>
              <div id="checks"></div>
            </div>
            <button id="toggle" title="SEO Inspector">🔍</button>`;

          const toggle = shadow.getElementById('toggle');
          const panel  = shadow.getElementById('panel');
          const checks = shadow.getElementById('checks');
          const score  = shadow.getElementById('score');
          let loaded   = false;

          toggle.addEventListener('click', () => {
            panel.classList.toggle('open');
            if (!loaded) {
              loaded = true;
              fetch('<?php echo $apiBase; ?>/inspector/audit', {
                method:'POST',
                headers:{'Content-Type':'application/json','X-WP-Nonce':'<?php echo $nonce; ?>'},
                body: JSON.stringify({post_id: <?php echo (int)$postId; ?>})
              })
              .then(r=>r.json())
              .then(data=>{
                score.textContent = data.summary.score + '%';
                checks.innerHTML = Object.entries(data.checks).map(([k,v])=>`
                  <div class="row">
                    <span class="label">${k.replace(/_/g,' ')}</span>
                    <span class="badge ${v.status}" title="${v.message}">${v.status.toUpperCase()}</span>
                  </div>`).join('');
              })
              .catch(()=>{ checks.innerHTML='<p style="color:red;font-size:12px">Error loading audit.</p>'; });
            }
          });
        })();
        </script>
        <?php
    }
}
