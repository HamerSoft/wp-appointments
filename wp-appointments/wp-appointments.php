<?php
/**
 * Plugin Name: WP Appointments
 * Plugin URI:  https://github.com/placeholder/wp-appointments
 * Description: Custom booking plugin for a solo massage therapist.
 * Version:     1.0.0
 * Author:      Ruben Hamers
 * Text Domain: wp-appointments
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAPPT_VERSION',    '1.0.0' );
define( 'WPAPPT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAPPT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAPPT_PLUGIN_FILE', __FILE__ );

/**
 * Autoloader.
 *
 * Maps WPAPPT_ class names to files under includes/ and admin/:
 *
 *   WPAPPT_Activator                → includes/class-activator.php
 *   WPAPPT_Model_Booking            → includes/models/class-booking.php
 *   WPAPPT_Controller_Booking       → includes/controllers/class-booking-controller.php
 *   WPAPPT_Service_Email            → includes/services/class-email-service.php
 *   WPAPPT_Helper_Sanitizer         → includes/helpers/class-sanitizer.php
 *   WPAPPT_Admin                    → admin/class-admin.php
 *   WPAPPT_Admin_Bookings_List_Table → admin/class-bookings-list-table.php
 */
spl_autoload_register( function ( string $class_name ): void {
	if ( strpos( $class_name, 'WPAPPT_' ) !== 0 ) {
		return;
	}

	// Strip prefix and lower-case everything.
	$stripped = strtolower( substr( $class_name, strlen( 'WPAPPT_' ) ) );
	// Underscores → dashes for the filename portion.
	$dashed   = str_replace( '_', '-', $stripped );

	// Determine subdirectory from second segment (Model, Controller, Service, Helper, Admin).
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
		$file = WPAPPT_PLUGIN_DIR . $subdir_map[ $prefix ] . 'class-' . $rest . '.php';
	} elseif ( $prefix === 'admin' && $rest === '' ) {
		// WPAPPT_Admin itself lives in admin/class-admin.php
		$file = WPAPPT_PLUGIN_DIR . 'admin/class-admin.php';
	} else {
		// Top-level includes: WPAPPT_Plugin, WPAPPT_Activator, etc.
		$file = WPAPPT_PLUGIN_DIR . 'includes/class-' . $dashed . '.php';
	}

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// ---------------------------------------------------------------------------
// Activation / deactivation hooks must be registered before the plugin runs.
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, [ 'WPAPPT_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'WPAPPT_Deactivator', 'deactivate' ] );

/**
 * Kick off the plugin after all plugins are loaded so that optional
 * integrations (Divi) can be detected.
 */
add_action( 'plugins_loaded', function (): void {
	WPAPPT_Plugin::get_instance();
} );
