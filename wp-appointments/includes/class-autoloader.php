<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin class autoloader.
 *
 * Separating path resolution into a static method (resolve_path) makes it
 * unit-testable without touching the filesystem or requiring a real WP install.
 *
 * Naming convention → file mapping:
 *
 *   WPAPPT_Plugin                    → includes/class-plugin.php
 *   WPAPPT_Activator                 → includes/class-activator.php
 *   WPAPPT_Model_Booking             → includes/models/class-booking.php
 *   WPAPPT_Controller_Admin_Ajax     → includes/controllers/class-admin-ajax.php
 *   WPAPPT_Service_Email             → includes/services/class-email.php
 *   WPAPPT_Helper_Sanitizer          → includes/helpers/class-sanitizer.php
 *   WPAPPT_Admin                     → admin/class-admin.php
 *   WPAPPT_Admin_Bookings_List_Table → admin/class-bookings-list-table.php
 */
class WPAPPT_Autoloader {

	/**
	 * Register this autoloader with SPL.
	 */
	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	/**
	 * Require the file for $class_name if it belongs to this plugin.
	 */
	public static function load( string $class_name ): void {
		$file = self::resolve_path( $class_name );

		if ( null !== $file && file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Resolve a WPAPPT_ class name to an absolute file path.
	 *
	 * Returns null for any class outside this plugin's namespace so the SPL
	 * chain continues to the next registered autoloader.
	 */
	public static function resolve_path( string $class_name ): ?string {
		if ( strpos( $class_name, 'WPAPPT_' ) !== 0 ) {
			return null;
		}

		// Strip prefix, lowercase, replace underscores with dashes.
		$stripped = strtolower( substr( $class_name, strlen( 'WPAPPT_' ) ) );
		$dashed   = str_replace( '_', '-', $stripped );

		// The first dash-segment determines the subdirectory.
		$segments = explode( '-', $dashed, 2 );
		$prefix   = $segments[0];
		$rest     = $segments[1] ?? '';

		$subdir_map = [
			'model'      => 'includes/models/',
			'controller' => 'includes/controllers/',
			'service'    => 'includes/services/',
			'helper'     => 'includes/helpers/',
			'admin'      => 'admin/',
		];

		if ( isset( $subdir_map[ $prefix ] ) && $rest !== '' ) {
			// Namespaced class: WPAPPT_Model_Booking → includes/models/class-booking.php
			return WPAPPT_PLUGIN_DIR . $subdir_map[ $prefix ] . 'class-' . $rest . '.php';
		}

		if ( 'admin' === $prefix && '' === $rest ) {
			// Bare admin class: WPAPPT_Admin → admin/class-admin.php
			return WPAPPT_PLUGIN_DIR . 'admin/class-admin.php';
		}

		// Top-level class: WPAPPT_Plugin → includes/class-plugin.php
		return WPAPPT_PLUGIN_DIR . 'includes/class-' . $dashed . '.php';
	}
}
