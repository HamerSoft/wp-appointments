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

	const SECTION           = 'wpappt_general';
	const SECTION_SMTP      = 'wpappt_smtp';
	const SECTION_REMINDERS = 'wpappt_reminders';

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
				'sanitize_callback' => [ $this, 'sanitize_booking_page' ],
				'default'           => 0,
			]
		);

		register_setting( 'wpappt_options', 'wpappt_reminders_enabled', [ 'type' => 'integer', 'sanitize_callback' => 'absint',              'default' => 1  ] );
		register_setting( 'wpappt_options', 'wpappt_reminder_days',    [ 'type' => 'integer', 'sanitize_callback' => 'absint',              'default' => 1  ] );

		register_setting( 'wpappt_options', 'wpappt_smtp_host',       [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
		register_setting( 'wpappt_options', 'wpappt_smtp_port',       [ 'type' => 'integer', 'sanitize_callback' => 'absint',              'default' => 587 ] );
		register_setting( 'wpappt_options', 'wpappt_smtp_encryption', [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field', 'default' => 'tls' ] );

		add_settings_section(
			self::SECTION,
			__( 'General Settings', 'wp-appointments' ),
			'__return_false',
			'wpappt_options'
		);

		add_settings_section(
			self::SECTION_REMINDERS,
			__( 'Appointment Reminders', 'wp-appointments' ),
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

		add_settings_field( 'wpappt_reminders_enabled', __( 'Send reminders',    'wp-appointments' ), [ $this, 'field_reminders_enabled' ], 'wpappt_options', self::SECTION_REMINDERS );
		add_settings_field( 'wpappt_reminder_days',    __( 'Days in advance',   'wp-appointments' ), [ $this, 'field_reminder_days'    ], 'wpappt_options', self::SECTION_REMINDERS );

		add_settings_field( 'wpappt_smtp_host',       __( 'SMTP Host',  'wp-appointments' ), [ $this, 'field_smtp_host'       ], 'wpappt_options', self::SECTION_SMTP );
		add_settings_field( 'wpappt_smtp_port',       __( 'SMTP Port',  'wp-appointments' ), [ $this, 'field_smtp_port'       ], 'wpappt_options', self::SECTION_SMTP );
		add_settings_field( 'wpappt_smtp_encryption', __( 'Encryption', 'wp-appointments' ), [ $this, 'field_smtp_encryption' ], 'wpappt_options', self::SECTION_SMTP );
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

	public function field_reminders_enabled(): void {
		$value = (int) get_option( 'wpappt_reminders_enabled', 1 );
		printf(
			'<label><input type="checkbox" name="wpappt_reminders_enabled" value="1"%s /> %s</label>
			<p class="description">%s</p>',
			checked( 1, $value, false ),
			esc_html__( 'Enabled', 'wp-appointments' ),
			esc_html__( 'Automatically email customers a reminder before their appointment.', 'wp-appointments' )
		);
	}

	public function field_reminder_days(): void {
		$value = (int) get_option( 'wpappt_reminder_days', 1 );
		printf(
			'<input type="number" name="wpappt_reminder_days" value="%d" min="1" max="14" class="small-text" />
			<p class="description">%s</p>',
			$value,
			esc_html__( 'How many days before the appointment to send the reminder. Default: 1 (the day before).', 'wp-appointments' )
		);
	}

	public function section_smtp_description(): void {
		echo '<p class="description">' . esc_html__( 'Connection settings for your domain email. Username and password must be set via wp-config.php constants or environment variables — they are not stored in the database.', 'wp-appointments' ) . '</p>';
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

	// -------------------------------------------------------------------------
	// Booking page validation
	// -------------------------------------------------------------------------

	public function sanitize_booking_page( $value ): int {
		$page_id = absint( $value );
		if ( $page_id && ! $this->booking_page_is_valid( $page_id ) ) {
			add_settings_error(
				'wpappt_booking_page',
				'wpappt_booking_page_invalid',
				__( 'The selected booking page is not published. Reschedule links in emails will be broken.', 'wp-appointments' ),
				'warning'
			);
		}
		return $page_id;
	}

	private function booking_page_is_valid( int $page_id ): bool {
		$post = get_post( $page_id );
		return $post && $post->post_type === 'page' && $post->post_status === 'publish';
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function render(): void {
		$page_id = (int) get_option( 'wpappt_booking_page', 0 );
		$show_page_warning = $page_id && ! $this->booking_page_is_valid( $page_id );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Appointments — Settings', 'wp-appointments' ); ?></h1>
			<?php if ( $show_page_warning ) : ?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'The selected booking page is not published. Reschedule links in emails will be broken.', 'wp-appointments' ); ?></p>
			</div>
			<?php endif; ?>
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
