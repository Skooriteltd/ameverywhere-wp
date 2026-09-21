<?php

namespace AmEveryWhere\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminMenu {

	public function registerMenu(): void {
		// Determine the lowest-privilege capability the user has to access the SPA
		$cap = 'manage_seo';
		if ( current_user_can( 'view_seo_reports' ) ) {
			$cap = 'view_seo_reports';
		} elseif ( current_user_can( 'manage_redirects' ) ) {
			$cap = 'manage_redirects';
		} elseif ( current_user_can( 'manage_options' ) ) {
			$cap = 'manage_options';
		}

		add_menu_page(
			'AmEveryWhere SEO',
			'AmEveryWhere',
			$cap,
			'ameverywhere',
			array( $this, 'renderAdminPage' ),
			'dashicons-chart-area',
			85
		);
	}

	public function enqueueAssets( string $hook ): void {
		// Only load assets on our plugin page
		if ( $hook !== 'toplevel_page_ameverywhere' ) {
			return;
		}

		$assetFile = AMEVERYWHERE_PLUGIN_DIR . 'build/index.asset.php';

		if ( file_exists( $assetFile ) ) {
			$assets = require $assetFile;
			wp_enqueue_script(
				'ameverywhere-admin-js',
				AMEVERYWHERE_PLUGIN_URL . 'build/index.js',
				$assets['dependencies'],
				$assets['version'],
				true
			);

			$adminConfig = array(
				'apiUrl'        => esc_url_raw( rest_url( 'ameverywhere/v1' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'setupComplete' => get_option( 'ameverywhere_setup_complete' ) ? '1' : '0',
				'isMultisite'   => is_multisite() ? '1' : '0',
			);

			wp_localize_script( 'ameverywhere-admin-js', 'amEveryWhereAdminConfig', $adminConfig );
		}

		wp_enqueue_style(
			'ameverywhere-admin-css',
			AMEVERYWHERE_PLUGIN_URL . 'build/index.css',
			array(),
			AMEVERYWHERE_VERSION
		);
		wp_style_add_data( 'ameverywhere-admin-css', 'rtl', 'replace' );
	}

	public function renderAdminPage(): void {
		echo '<div class="wrap"><div id="ameverywhere-admin-app"></div></div>';
	}
}
