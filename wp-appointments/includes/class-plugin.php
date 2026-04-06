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
		// Host/port/encryption: env var → wp-config.php constant → settings page DB option.
		$host = $this->smtp_setting( 'WPAPPT_SMTP_HOST',       'wpappt_smtp_host',       '' );
		$port = (int) $this->smtp_setting( 'WPAPPT_SMTP_PORT', 'wpappt_smtp_port',       '587' );
		$enc  = $this->smtp_setting( 'WPAPPT_SMTP_ENCRYPTION', 'wpappt_smtp_encryption', 'tls' );

		if ( '' === $host ) {
			return; // No SMTP configured — leave WordPress default alone.
		}

		// Credentials: env var → wp-config.php constant only. Never stored in DB.
		$username = $this->smtp_credential( 'WPAPPT_SMTP_USERNAME' );
		$password = $this->smtp_credential( 'WPAPPT_SMTP_PASSWORD' );

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
	 * Resolve a non-sensitive SMTP setting.
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

	/**
	 * Resolve a sensitive SMTP credential.
	 * Priority: environment variable → wp-config.php constant only.
	 * Credentials are never stored in the database.
	 */
	private function smtp_credential( string $constant ): string {
		$env = getenv( $constant );
		if ( false !== $env && '' !== $env ) {
			return $env;
		}
		if ( defined( $constant ) ) {
			return (string) constant( $constant );
		}
		return '';
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
			'apiUrl'  => esc_url_raw( rest_url( 'wpappt/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'strings' => $this->widget_strings(),
		] );
	}

	// -------------------------------------------------------------------------
	// Widget string localisation
	// -------------------------------------------------------------------------

	/**
	 * All front-end strings passed to the booking widget via wp_localize_script.
	 * Each value goes through __() so it is picked up by the text domain and
	 * translated at runtime when a matching .mo file is present.
	 *
	 * @return array<string, string|string[]>
	 */
	private function widget_strings(): array {
		return [
			// Progress bar step labels.
			'progressService' => __( 'Service',  'wp-appointments' ),
			'progressDate'    => __( 'Date',     'wp-appointments' ),
			'progressTime'    => __( 'Time',     'wp-appointments' ),
			'progressDetails' => __( 'Details',  'wp-appointments' ),
			'progressConfirm' => __( 'Confirm',  'wp-appointments' ),

			// Step headings.
			'titleService' => __( 'Choose a service', 'wp-appointments' ),
			'titleDate'    => __( 'Choose a date',    'wp-appointments' ),
			'titleTime'    => __( 'Choose a time',    'wp-appointments' ),
			'titleDetails' => __( 'Your details',     'wp-appointments' ),
			'titleReview'  => __( 'Review your booking', 'wp-appointments' ),
			'titleSuccess' => __( 'Booking request sent!', 'wp-appointments' ),

			// Navigation buttons.
			'btnNext'        => __( 'Next →',          'wp-appointments' ),
			'btnBack'        => __( '← Back',           'wp-appointments' ),
			'btnEditDetails' => __( '← Edit details',   'wp-appointments' ),
			'btnReview'      => __( 'Review booking →', 'wp-appointments' ),
			'btnConfirm'     => __( 'Confirm booking',  'wp-appointments' ),
			'btnSending'     => __( 'Sending…',         'wp-appointments' ),

			// Form field labels.
			'labelName'     => __( 'Full name',               'wp-appointments' ),
			'labelEmail'    => __( 'Email address',           'wp-appointments' ),
			'labelPhone'    => __( 'Phone number',            'wp-appointments' ),
			'labelInjury'   => __( 'Injury or health notes',  'wp-appointments' ),
			'labelComments' => __( 'Additional comments',     'wp-appointments' ),
			'labelOptional' => __( '(optional)',              'wp-appointments' ),

			// Client-side validation errors.
			'errNameRequired'  => __( 'Please enter your name.',                                               'wp-appointments' ),
			'errEmailRequired' => __( 'Please enter your email address.',                                      'wp-appointments' ),
			'errEmailInvalid'  => __( 'Please enter a valid email address.',                                   'wp-appointments' ),
			'errPhoneRequired' => __( 'Please enter your phone number.',                                       'wp-appointments' ),
			'errPhoneInvalid'  => __( 'Please enter a valid phone number (digits, spaces, +, – allowed).', 'wp-appointments' ),

			// Booking summary row labels.
			'summaryService'     => __( 'Service',       'wp-appointments' ),
			'summaryDate'        => __( 'Date',          'wp-appointments' ),
			'summaryTime'        => __( 'Time',          'wp-appointments' ),
			'summaryName'        => __( 'Name',          'wp-appointments' ),
			'summaryEmail'       => __( 'Email',         'wp-appointments' ),
			'summaryPhone'       => __( 'Phone',         'wp-appointments' ),
			'summaryHealthNotes' => __( 'Health notes',  'wp-appointments' ),
			'summaryComments'    => __( 'Comments',      'wp-appointments' ),

			// Status / notice messages.
			'loading'          => __( 'Loading…',                                                                                               'wp-appointments' ),
			'noticeNoServices' => __( 'No services are currently available.',                                                                        'wp-appointments' ),
			'noticeNoSlots'    => __( 'No times are available on this date. Go back and choose another day.',                                        'wp-appointments' ),
			'noticeReviewInfo' => __( "Once confirmed, your booking request is sent for approval. You'll receive an email when it's confirmed.",     'wp-appointments' ),
			'errGeneral'       => __( 'Something went wrong. Please try again.',                                                                     'wp-appointments' ),
			'errLoadServices'  => __( 'Could not load services. Please refresh the page and try again.',                                             'wp-appointments' ),
			'errLoadSlots'     => __( 'Could not load available times. Please try again.',                                                           'wp-appointments' ),

			// Success screen — use {name}, {service}, {date}, {time}, {email} as placeholders.
			'successMessage' => __( 'Thank you, {name}. Your request for {service} on {date} at {time} has been received.',             'wp-appointments' ),
			'successEmail'   => __( "You'll get a confirmation email at {email} once your appointment is approved.",                    'wp-appointments' ),

			// Accessibility labels.
			'ariaBookingSteps' => __( 'Booking steps',   'wp-appointments' ),
			'ariaDatePicker'   => __( 'Date picker',     'wp-appointments' ),
			'ariaPrevMonth'    => __( 'Previous month',  'wp-appointments' ),
			'ariaNextMonth'    => __( 'Next month',      'wp-appointments' ),
			'ariaSelected'     => __( ', selected',      'wp-appointments' ),

			// Calendar month and day names (arrays passed as-is).
			'monthNames' => [
				__( 'January',   'wp-appointments' ), __( 'February',  'wp-appointments' ),
				__( 'March',     'wp-appointments' ), __( 'April',     'wp-appointments' ),
				__( 'May',       'wp-appointments' ), __( 'June',      'wp-appointments' ),
				__( 'July',      'wp-appointments' ), __( 'August',    'wp-appointments' ),
				__( 'September', 'wp-appointments' ), __( 'October',   'wp-appointments' ),
				__( 'November',  'wp-appointments' ), __( 'December',  'wp-appointments' ),
			],
			// Short day names starting Sunday — two letters each.
			'dayNames' => [
				__( 'Su', 'wp-appointments' ), __( 'Mo', 'wp-appointments' ),
				__( 'Tu', 'wp-appointments' ), __( 'We', 'wp-appointments' ),
				__( 'Th', 'wp-appointments' ), __( 'Fr', 'wp-appointments' ),
				__( 'Sa', 'wp-appointments' ),
			],
			// Full day names for the date display string (starting Sunday).
			'dayNamesLong' => [
				__( 'Sunday',    'wp-appointments' ), __( 'Monday',    'wp-appointments' ),
				__( 'Tuesday',   'wp-appointments' ), __( 'Wednesday', 'wp-appointments' ),
				__( 'Thursday',  'wp-appointments' ), __( 'Friday',    'wp-appointments' ),
				__( 'Saturday',  'wp-appointments' ),
			],

			// Duration unit abbreviations.
			'durationMin'  => __( 'min', 'wp-appointments' ),
			'durationHour' => __( 'h',   'wp-appointments' ),

			// Time format: '24h' (Dutch) or '12h' (AM/PM).
			// Translators: use '24h' for 24-hour clock or '12h' for AM/PM.
			'timeFormat' => __( '24h', 'wp-appointments' ),
		];
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

		// Reminder cron handler.
		( new WPAPPT_Service_Reminder() )->init();

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
