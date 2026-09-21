<?php

namespace AmEveryWhere\Modules\TechnicalSeo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FaviconAudit: Warns admins when no site icon (favicon) has been configured.
 *
 * WordPress outputs favicon tags natively once a site icon is set in
 * Customizer → Site Identity. This module audits that setting and
 * shows a dismissible admin notice with a direct link to fix it.
 */
class FaviconAudit {

	private const DISMISSED_OPTION = 'ameverywhere_favicon_notice_dismissed';

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'maybeShowNotice' ) );
		add_action( 'wp_ajax_ameverywhere_dismiss_favicon_notice', array( $this, 'dismissNotice' ) );
		// Auto-clear the dismissed flag if a site icon gets set
		add_action( 'update_option_site_icon', array( $this, 'clearDismissed' ) );
	}

	/**
	 * Show a dismissible admin notice if no site icon is configured.
	 */
	public function maybeShowNotice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( self::DISMISSED_OPTION ) ) {
			return;
		}
		if ( ! empty( get_site_icon_url( 32 ) ) ) {
			return; // Already set — nothing to do
		}

		$customizerUrl = admin_url( 'customize.php?autofocus[control]=site_icon' );
		$nonce         = wp_create_nonce( 'ameverywhere_dismiss_favicon_notice' );

		printf(
			'<div class="notice notice-warning is-dismissible ameverywhere-favicon-notice" data-nonce="%s">
                <p>
                    <strong>AmEveryWhere:</strong> No favicon (site icon) is set for this site.
                    A favicon improves brand recognition in browser tabs, bookmarks, and search results.
                    &nbsp;<a href="%s">Set your favicon in the Customizer &rarr;</a>
                </p>
            </div>
            <script>
            (function(){
                document.addEventListener("DOMContentLoaded", function(){
                    var n = document.querySelector(".ameverywhere-favicon-notice");
                    if (!n) return;
                    n.addEventListener("click", function(e){
                        if (!e.target.classList.contains("notice-dismiss")) return;
                        var fd = new FormData();
                        fd.append("action", "ameverywhere_dismiss_favicon_notice");
                        fd.append("nonce", n.dataset.nonce);
                        fetch(ajaxurl, { method: "POST", body: fd });
                    });
                });
            })();
            </script>',
			esc_attr( $nonce ),
			esc_url( $customizerUrl )
		);
	}

	/**
	 * AJAX: persist the dismissal so the notice does not reappear.
	 */
	public function dismissNotice(): void {
		check_ajax_referer( 'ameverywhere_dismiss_favicon_notice', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}
		update_option( self::DISMISSED_OPTION, true, false );
		wp_send_json_success();
	}

	/**
	 * Clear the dismissed flag when a site icon is set so we never
	 * re-nag if the icon is later removed and the user needs reminding.
	 */
	public function clearDismissed(): void {
		delete_option( self::DISMISSED_OPTION );
	}
}
