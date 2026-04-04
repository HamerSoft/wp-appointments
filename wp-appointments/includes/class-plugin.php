<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central orchestrator — singleton.
 *
 * Responsible for loading all subsystems and registering their hooks.
 * Additional subsystems are wired in as each build step is completed.
 */
class WPAPPT_Plugin {

	private static ?WPAPPT_Plugin $instance = null;

	private function __construct() {
		$this->load_subsystems();
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// Subsystem registration
	// -------------------------------------------------------------------------

	private function load_subsystems(): void {
		// Admin panel — only in the WordPress back-end.
		if ( is_admin() ) {
			( new WPAPPT_Admin() )->init();
			( new WPAPPT_Controller_Admin_Ajax() )->init();
		}

		// REST API routes — registered on rest_api_init.
		// Step 5: add_action( 'rest_api_init', [ new WPAPPT_Rest_Api(), 'register_routes' ] );

		// Divi module + shortcode — Step 9.
	}
}
