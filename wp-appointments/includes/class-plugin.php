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

	public function configure_smtp( $phpmailer ): void {
		// Resolution order: env var → wp-config.php constant → settings page DB option.
		$host     = $this->smtp_setting( 'WPAPPT_SMTP_HOST',       'wpappt_smtp_host',       '' );
		$port     = (int) $this->smtp_setting( 'WPAPPT_SMTP_PORT', 'wpappt_smtp_port',       '587' );
		$enc      = $this->smtp_setting( 'WPAPPT_SMTP_ENCRYPTION', 'wpappt_smtp_encryption', 'tls' );
		$username = $this->smtp_setting( 'WPAPPT_SMTP_USERNAME',   'wpappt_smtp_username',   '' );
		$password = $this->smtp_setting( 'WPAPPT_SMTP_PASSWORD',   'wpappt_smtp_password',   '' );

		if ( '' === $host ) {
			return; // No SMTP configured — leave WordPress default alone.
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $host;
		$phpmailer->Port       = $port;
		$phpmailer->SMTPSecure = $enc;

		if ( '' !== $username && '' !== $password ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $username;
			$phpmailer->Password = $password;
		}
	}

	/**
	 * Resolve a single SMTP setting.
	 * Priority: environment variable → wp-config.php constant → database option.
	 */
	private function smtp_setting( string $constant, string $option, string $default ): string {
		$env = getenv( $constant );
		if ( false !== $env && '' !== $env ) {
			return $env;
		}
		if ( defined( $constant ) ) {
			return (string) constant( $constant );
		}
		return (string) get_option( $option, $default );
	}

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

		// SMTP configuration.
		add_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );

		// Frontend booking widget assets.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_widget_assets' ] );

		// Shortcode fallback: [wpappt_booking].
		add_shortcode( 'wpappt_booking', static function (): string {
			return '<div id="wpappt-booking-widget"></div>';
		} );

		// Divi module — registered after Divi's builder classes are loaded.
		// The action never fires when Divi is inactive, so this is safe on any site.
		add_action( 'et_builder_ready', static function (): void {
			require_once WPAPPT_PLUGIN_DIR . 'includes/class-divi-module.php';
			new WPAPPT_Divi_Module();
		} );
	}
}
