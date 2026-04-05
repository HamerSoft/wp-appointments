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

	// -------------------------------------------------------------------------
	// Frontend assets
	// -------------------------------------------------------------------------

	public function enqueue_widget_assets(): void {
		wp_enqueue_style(
			'wpappt-widget',
			WPAPPT_PLUGIN_URL . 'assets/css/booking-widget.css',
			[],
			WPAPPT_VERSION
		);

		wp_enqueue_script(
			'wpappt-widget',
			WPAPPT_PLUGIN_URL . 'assets/js/booking-widget.js',
			[],
			WPAPPT_VERSION,
			true  // load in footer
		);

		wp_localize_script( 'wpappt-widget', 'WPAppt', [
			'apiUrl' => esc_url_raw( rest_url( 'wpappt/v1/' ) ),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
		] );
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

		// Token service (priority 5) — must init before email service so it
		// fires wpappt_booking_confirmed before the email listener (priority 10).
		( new WPAPPT_Service_Token() )->init();

		// Email service.
		( new WPAPPT_Service_Email() )->init();

		// REST API routes.
		add_action( 'rest_api_init', [ new WPAPPT_Rest_Api(), 'register_routes' ] );

		// Frontend booking widget assets.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_widget_assets' ] );

		// Divi module + shortcode — Step 9.
	}
}
