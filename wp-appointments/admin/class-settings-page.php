<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page — uses the WP Settings API.
 *
 * Options stored individually (not serialized) to avoid object injection risk:
 *   wpappt_admin_email   — notification recipient
 *   wpappt_sender_name   — From name used in all plugin emails
 *   wpappt_booking_page  — page ID where the booking widget is embedded
 */
class WPAPPT_Admin_Settings_Page {

	const SECTION      = 'wpappt_general';
	const SECTION_SMTP = 'wpappt_smtp';

	public function __construct() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function register_settings(): void {
		register_setting(
			'wpappt_options',
			'wpappt_admin_email',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => get_option( 'admin_email' ),
			]
		);

		register_setting(
			'wpappt_options',
			'wpappt_sender_name',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => get_bloginfo( 'name' ),
			]
		);

		register_setting(
			'wpappt_options',
			'wpappt_booking_page',
			[
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			]
		);

		register_setting( 'wpappt_options', 'wpappt_smtp_host',       [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
		register_setting( 'wpappt_options', 'wpappt_smtp_port',       [ 'type' => 'integer', 'sanitize_callback' => 'absint',              'default' => 587 ] );
		register_setting( 'wpappt_options', 'wpappt_smtp_encryption', [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field', 'default' => 'tls' ] );
		register_setting( 'wpappt_options', 'wpappt_smtp_username',   [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
		register_setting( 'wpappt_options', 'wpappt_smtp_password',   [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );

		add_settings_section(
			self::SECTION,
			__( 'General Settings', 'wp-appointments' ),
			'__return_false',
			'wpappt_options'
		);

		add_settings_section(
			self::SECTION_SMTP,
			__( 'Outgoing Email (SMTP)', 'wp-appointments' ),
			[ $this, 'section_smtp_description' ],
			'wpappt_options'
		);

		add_settings_field(
			'wpappt_admin_email',
			__( 'Notification Email', 'wp-appointments' ),
			[ $this, 'field_admin_email' ],
			'wpappt_options',
			self::SECTION
		);

		add_settings_field(
			'wpappt_sender_name',
			__( 'Sender Name', 'wp-appointments' ),
			[ $this, 'field_sender_name' ],
			'wpappt_options',
			self::SECTION
		);

		add_settings_field(
			'wpappt_booking_page',
			__( 'Booking Page', 'wp-appointments' ),
			[ $this, 'field_booking_page' ],
			'wpappt_options',
			self::SECTION
		);

		add_settings_field( 'wpappt_smtp_host',       __( 'SMTP Host',       'wp-appointments' ), [ $this, 'field_smtp_host'       ], 'wpappt_options', self::SECTION_SMTP );
		add_settings_field( 'wpappt_smtp_port',       __( 'SMTP Port',       'wp-appointments' ), [ $this, 'field_smtp_port'       ], 'wpappt_options', self::SECTION_SMTP );
		add_settings_field( 'wpappt_smtp_encryption', __( 'Encryption',      'wp-appointments' ), [ $this, 'field_smtp_encryption' ], 'wpappt_options', self::SECTION_SMTP );
		add_settings_field( 'wpappt_smtp_username',   __( 'SMTP Username',   'wp-appointments' ), [ $this, 'field_smtp_username'   ], 'wpappt_options', self::SECTION_SMTP );
		add_settings_field( 'wpappt_smtp_password',   __( 'SMTP Password',   'wp-appointments' ), [ $this, 'field_smtp_password'   ], 'wpappt_options', self::SECTION_SMTP );
	}

	// -------------------------------------------------------------------------
	// Field callbacks
	// -------------------------------------------------------------------------

	public function field_admin_email(): void {
		$value = get_option( 'wpappt_admin_email', get_option( 'admin_email' ) );
		printf(
			'<input type="email" name="wpappt_admin_email" value="%s" class="regular-text" />
			<p class="description">%s</p>',
			esc_attr( $value ),
			esc_html__( 'All booking notifications will be sent to this address.', 'wp-appointments' )
		);
	}

	public function field_sender_name(): void {
		$value = get_option( 'wpappt_sender_name', get_bloginfo( 'name' ) );
		printf(
			'<input type="text" name="wpappt_sender_name" value="%s" class="regular-text" />
			<p class="description">%s</p>',
			esc_attr( $value ),
			esc_html__( 'Appears in the From field of all emails sent by this plugin.', 'wp-appointments' )
		);
	}

	public function field_booking_page(): void {
		$value = (int) get_option( 'wpappt_booking_page', 0 );
		wp_dropdown_pages( [
			'name'             => 'wpappt_booking_page',
			'selected'         => $value,
			'show_option_none' => __( '— Select a page —', 'wp-appointments' ),
			'option_none_value' => 0,
		] );
		echo '<p class="description">' . esc_html__( 'The page where the booking widget is embedded. Used to build reschedule links.', 'wp-appointments' ) . '</p>';
	}

	public function section_smtp_description(): void {
		echo '<p class="description">' . esc_html__( 'Leave blank to use the server default. Fill in to send via your own email provider (Gmail, Mailgun, etc.).', 'wp-appointments' ) . '</p>';
	}

	public function field_smtp_host(): void {
		$value = get_option( 'wpappt_smtp_host', '' );
		printf(
			'<input type="text" name="wpappt_smtp_host" value="%s" class="regular-text" placeholder="e.g. smtp.gmail.com" />',
			esc_attr( $value )
		);
	}

	public function field_smtp_port(): void {
		$value = get_option( 'wpappt_smtp_port', 587 );
		printf(
			'<input type="number" name="wpappt_smtp_port" value="%d" class="small-text" />
			<p class="description">%s</p>',
			(int) $value,
			esc_html__( '587 for TLS (recommended), 465 for SSL, 25 for none.', 'wp-appointments' )
		);
	}

	public function field_smtp_encryption(): void {
		$value = get_option( 'wpappt_smtp_encryption', 'tls' );
		$options = [ 'tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL', '' => 'None' ];
		echo '<select name="wpappt_smtp_encryption">';
		foreach ( $options as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	public function field_smtp_username(): void {
		$value = get_option( 'wpappt_smtp_username', '' );
		printf(
			'<input type="text" name="wpappt_smtp_username" value="%s" class="regular-text" autocomplete="off" />',
			esc_attr( $value )
		);
	}

	public function field_smtp_password(): void {
		$value = get_option( 'wpappt_smtp_password', '' );
		printf(
			'<input type="password" name="wpappt_smtp_password" value="%s" class="regular-text" autocomplete="new-password" />
			<p class="description">%s</p>',
			esc_attr( $value ),
			esc_html__( 'Stored in the database. For Gmail, use an App Password (not your account password).', 'wp-appointments' )
		);
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Appointments — Settings', 'wp-appointments' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpappt_options' );
				do_settings_sections( 'wpappt_options' );
				submit_button( __( 'Save Settings', 'wp-appointments' ) );
				?>
			</form>
		</div>
		<?php
	}
}
