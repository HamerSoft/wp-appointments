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

// Rate-limiting defaults — override in wp-config.php before the plugin loads.
defined( 'WPAPPT_RATE_LIMIT'  ) || define( 'WPAPPT_RATE_LIMIT',  5 );
defined( 'WPAPPT_RATE_WINDOW' ) || define( 'WPAPPT_RATE_WINDOW', 10 * MINUTE_IN_SECONDS );

// Bootstrap the autoloader — must be required manually before it can self-load.
require_once WPAPPT_PLUGIN_DIR . 'includes/class-autoloader.php';
WPAPPT_Autoloader::register();

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
	load_plugin_textdomain(
		'wp-appointments',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
	WPAPPT_Plugin::get_instance();
} );
