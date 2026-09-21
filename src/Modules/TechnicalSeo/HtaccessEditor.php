<?php

namespace AmEveryWhere\Modules\TechnicalSeo;

/**
 * HtaccessEditor
 *
 * Provides a safe admin interface for reading and editing the .htaccess file.
 * Creates timestamped backups before every save and supports one-click restore.
 *
 * BL-014
 */
class HtaccessEditor {

	private const BACKUP_LIMIT = 20;

	private function getHtaccessPath(): string {
		return ( defined( 'ABSPATH' ) ? ABSPATH : '' ) . '.htaccess';
	}

	private function getBackupDir(): string {
		return ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '' ) . '/ameverywhere-backups/htaccess/';
	}

	private function initFilesystem(): bool {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return ! empty( $wp_filesystem );
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	// ── REST routes ───────────────────────────────────────────────────────────

	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'ameverywhere/v1',
			'/htaccess',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'readHtaccessEndpoint' ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveHtaccessEndpoint' ),
					'permission_callback' => $adminCap,
				),
			)
		);

		register_rest_route(
			'ameverywhere/v1',
			'/htaccess/restore',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'restoreEndpoint' ),
				'permission_callback' => $adminCap,
			)
		);
	}

	public function readHtaccessEndpoint( \WP_REST_Request $request ): \WP_REST_Response {
		$path     = $this->getHtaccessPath();
		$content  = $this->readHtaccess();
		$readable = file_exists( $path ) && is_readable( $path );
		$writable = file_exists( $path ) && is_writable( $path );

		return rest_ensure_response(
			array(
				'content'  => $content,
				'readable' => $readable,
				'writable' => $writable,
				'path'     => $path,
				'backups'  => $this->listBackups(),
				'warnings' => $readable ? $this->validateSyntax( $content ) : array(),
			)
		);
	}

	public function saveHtaccessEndpoint( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params  = $request->get_json_params();
		$content = $params['content'] ?? '';

		if ( ! is_string( $content ) ) {
			return new \WP_Error( 'invalid_content', 'Content must be a string.', array( 'status' => 400 ) );
		}

		$dangerousPatterns = array(
			'/AddHandler\s+.*php/i',
			'/AddType\s+.*php/i',
			'/php_value\s+disable_functions/i',
			'/auto_prepend_file/i',
			'/auto_append_file/i',
		);

		foreach ( $dangerousPatterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return new \WP_Error(
					'unsafe_content',
					__( 'Save rejected: the .htaccess content contains a potentially dangerous PHP execution directive.', 'ameverywhere' ),
					array( 'status' => 422 )
				);
			}
		}

		$warnings = $this->validateSyntax( $content );

		$saved = $this->saveHtaccess( $content );
		if ( ! $saved ) {
			return new \WP_Error( 'save_failed', '.htaccess could not be written. Check file permissions.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'warnings' => $warnings,
				'backups'  => $this->listBackups(),
			)
		);
	}

	public function restoreEndpoint( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params   = $request->get_json_params();
		$filename = sanitize_file_name( $params['filename'] ?? '' );

		if ( empty( $filename ) ) {
			return new \WP_Error( 'missing_filename', 'Backup filename is required.', array( 'status' => 400 ) );
		}

		$restored = $this->restoreHtaccess( $filename );
		if ( ! $restored ) {
			return new \WP_Error( 'restore_failed', 'Backup not found or could not be restored.', array( 'status' => 400 ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	// ── File operations ───────────────────────────────────────────────────────

	public function readHtaccess(): string {
		$path = $this->getHtaccessPath();
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return '';
		}

		if ( $this->initFilesystem() ) {
			global $wp_filesystem;
			$contents = $wp_filesystem->get_contents( $path );
			if ( $contents !== false ) {
				return (string) $contents;
			}
		}

		return (string) file_get_contents( $path );
	}

	public function saveHtaccess( string $content ): bool {
		$path      = $this->getHtaccessPath();
		$backupDir = $this->getBackupDir();

		// Ensure backup directory exists
		if ( ! is_dir( $backupDir ) ) {
			wp_mkdir_p( $backupDir );
			file_put_contents( $backupDir . 'index.php', '<?php // Silence is golden' );
		}

		// Create timestamped backup of existing file
		if ( file_exists( $path ) && is_readable( $path ) ) {
			$backupFile = $backupDir . 'htaccess-' . date( 'Y-m-d-His' ) . '.txt';
			@copy( $path, $backupFile );
			$this->pruneBackups( $backupDir );
		}

		if ( $this->initFilesystem() ) {
			global $wp_filesystem;
			if ( $wp_filesystem->put_contents( $path, $content ) ) {
				return true;
			}
		}

		return (bool) file_put_contents( $path, $content );
	}

	public function restoreHtaccess( string $backupFilename ): bool {
		// Security: only allow basenames, no path traversal
		$backupFilename = basename( $backupFilename );
		if ( ! preg_match( '/^htaccess-[\d\-]+\.txt$/', $backupFilename ) ) {
			return false;
		}

		$backupPath = $this->getBackupDir() . $backupFilename;
		if ( ! file_exists( $backupPath ) || ! is_readable( $backupPath ) ) {
			return false;
		}

		$content = file_get_contents( $backupPath );
		return $content !== false && $this->saveHtaccess( $content );
	}

	public function listBackups(): array {
		$backupDir = $this->getBackupDir();
		if ( ! is_dir( $backupDir ) ) {
			return array();
		}

		$files = glob( $backupDir . 'htaccess-*.txt' );
		if ( ! $files ) {
			return array();
		}

		rsort( $files ); // newest first
		$files = array_slice( $files, 0, self::BACKUP_LIMIT );

		return array_map(
			function ( $path ) {
				return array(
					'filename'   => basename( $path ),
					'created_at' => date( 'Y-m-d H:i:s', (int) filemtime( $path ) ),
					'size'       => filesize( $path ),
				);
			},
			$files
		);
	}

	// ── Syntax validation ─────────────────────────────────────────────────────

	/**
	 * Basic structural validation; returns array of warning strings (non-blocking).
	 */
	public function validateSyntax( string $content ): array {
		$warnings = array();
		$lines    = explode( "\n", $content );

		$openTags    = array();
		$closingTags = array();

		foreach ( $lines as $lineNum => $line ) {
			$trimmed = trim( $line );

			// Check for unmatched opening directives
			if ( preg_match( '/^<(IfModule|Directory|Files|Location|VirtualHost)[^>]*>/i', $trimmed, $m ) ) {
				$openTags[] = array(
					'tag'  => $m[1],
					'line' => $lineNum + 1,
				);
			}
			if ( preg_match( '/^<\/(IfModule|Directory|Files|Location|VirtualHost)>/i', $trimmed, $m ) ) {
				$closingTags[] = array(
					'tag'  => $m[1],
					'line' => $lineNum + 1,
				);
			}

			// Flag Disallow All — dangerous if in .htaccess as a robots directive
			if ( stripos( $trimmed, 'Deny from all' ) !== false && stripos( $trimmed, '#' ) !== 0 ) {
				$warnings[] = 'Line ' . ( $lineNum + 1 ) . ": 'Deny from all' detected — verify this is intentional.";
			}
		}

		if ( count( $openTags ) !== count( $closingTags ) ) {
			$warnings[] = 'Unmatched directive tags detected (open: ' . count( $openTags ) . ', close: ' . count( $closingTags ) . '). Review block nesting.';
		}

		return $warnings;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function pruneBackups( string $dir ): void {
		$files = glob( $dir . 'htaccess-*.txt' );
		if ( ! $files || count( $files ) <= self::BACKUP_LIMIT ) {
			return;
		}

		rsort( $files );
		$toDelete = array_slice( $files, self::BACKUP_LIMIT );
		foreach ( $toDelete as $f ) {
			@unlink( $f );
		}
	}
}
