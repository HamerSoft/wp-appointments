<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends all transactional emails for the plugin.
 *
 * Hooks into action hooks fired by the booking and reschedule controllers
 * so this class stays decoupled from the rest of the plugin.
 *
 * Reschedule links are passed in by the token service (Step 7). Every
 * send_* method that can carry a reschedule link accepts one as an optional
 * argument; it defaults to an empty string so Step 6 is fully functional
 * before the token service is wired up.
 */
class WPAPPT_Service_Email {

	private WPAPPT_Model_Booking $booking_model;
	private WPAPPT_Model_Service $service_model;

	public function __construct(
		?WPAPPT_Model_Booking $booking_model = null,
		?WPAPPT_Model_Service $service_model = null
	) {
		$this->booking_model = $booking_model ?? new WPAPPT_Model_Booking();
		$this->service_model = $service_model ?? new WPAPPT_Model_Service();
	}

	// -------------------------------------------------------------------------
	// Hook registration
	// -------------------------------------------------------------------------

	public function init(): void {
		// New booking submitted via REST API.
		add_action( 'wpappt_booking_created', [ $this, 'on_booking_created' ] );

		// Admin cancels a booking (confirmed fires separately via token service).
		add_action( 'wpappt_booking_status_changed', [ $this, 'on_status_changed' ], 10, 4 );

		// Token service fires this (priority 5) after generating a reschedule link.
		add_action( 'wpappt_booking_confirmed', [ $this, 'on_booking_confirmed' ], 10, 3 );

		// Reschedule controller fires this after a successful reschedule.
		add_action( 'wpappt_booking_rescheduled', [ $this, 'on_rescheduled' ], 10, 2 );

		// Admin sends a manual follow-up from the booking detail page.
		add_action( 'wpappt_send_followup_email', [ $this, 'on_followup' ], 10, 3 );

		// Daily cron reminder — fired by WPAPPT_Service_Reminder.
		add_action( 'wpappt_booking_reminder', [ $this, 'on_reminder' ] );
	}

	// -------------------------------------------------------------------------
	// Hook callbacks
	// -------------------------------------------------------------------------

	public function on_booking_created( int $booking_id ): void {
		$this->send_booking_received_customer( $booking_id );
		$this->send_booking_received_admin( $booking_id );
	}

	public function on_status_changed( int $booking_id, string $new_status, string $actor = '', array $attachments = [] ): void {
		// 'confirmed' is handled by on_booking_confirmed() — the token service
		// fires wpappt_booking_confirmed (priority 5) before this listener runs,
		// so the confirmation email always carries a fresh reschedule link.
		match ( $new_status ) {
			'cancelled' => $this->send_booking_cancelled( $booking_id, $attachments ),
			default     => null,
		};
	}

	public function on_booking_confirmed( int $booking_id, string $reschedule_link, array $attachments = [] ): void {
		$this->send_booking_confirmed( $booking_id, $reschedule_link, $attachments );
	}

	public function on_rescheduled( int $booking_id, string $reschedule_link ): void {
		$this->send_reschedule_customer( $booking_id, $reschedule_link );
		$this->send_reschedule_admin( $booking_id );
	}

	public function on_followup( int $booking_id, string $message, array $attachments = [] ): void {
		$this->send_followup( $booking_id, $message, $attachments );
	}

	public function on_reminder( int $booking_id ): void {
		$this->send_reminder_customer( $booking_id );
	}

	// -------------------------------------------------------------------------
	// Public send methods
	// -------------------------------------------------------------------------

	public function send_booking_received_customer( int $booking_id ): bool {
		$data = $this->load_booking_data( $booking_id );
		if ( null === $data ) {
			return false;
		}

		return $this->send(
			$data['booking']['customer_email'],
			sprintf(
				/* translators: %s: site name */
				__( '[%s] Booking request received', 'wp-appointments' ),
				$data['site_name']
			),
			'booking-received-customer',
			$data
		);
	}

	public function send_booking_received_admin( int $booking_id ): bool {
		$data = $this->load_booking_data( $booking_id );
		if ( null === $data ) {
			return false;
		}

		return $this->send(
			$data['admin_email'],
			sprintf(
				/* translators: %s: customer name */
				__( 'New booking request from %s', 'wp-appointments' ),
				$data['booking']['customer_name']
			),
			'booking-received-admin',
			$data
		);
	}

	/**
	 * @param string $reschedule_link Raw reschedule URL (supplied by token service in Step 7).
	 */
	public function send_booking_confirmed( int $booking_id, string $reschedule_link = '', array $attachments = [] ): bool {
		$data = $this->load_booking_data( $booking_id );
		if ( null === $data ) {
			return false;
		}

		$data['reschedule_link'] = $reschedule_link;

		return $this->send(
			$data['booking']['customer_email'],
			sprintf(
				/* translators: %s: site name */
				__( '[%s] Your booking is confirmed', 'wp-appointments' ),
				$data['site_name']
			),
			'booking-confirmed',
			$data,
			$attachments
		);
	}

	public function send_booking_cancelled( int $booking_id, array $attachments = [] ): bool {
		$data = $this->load_booking_data( $booking_id );
		if ( null === $data ) {
			return false;
		}

		return $this->send(
			$data['booking']['customer_email'],
			sprintf(
				/* translators: %s: site name */
				__( '[%s] Your booking has been cancelled', 'wp-appointments' ),
				$data['site_name']
			),
			'booking-cancelled',
			$data,
			$attachments
		);
	}

	/**
	 * Sent to the customer after they reschedule.
	 *
	 * @param string $reschedule_link Fresh reschedule link (for next reschedule).
	 */
	public function send_reschedule_customer( int $booking_id, string $reschedule_link = '' ): bool {
		$data = $this->load_booking_data( $booking_id );
		if ( null === $data ) {
			return false;
		}

		$data['reschedule_link'] = $reschedule_link;

		return $this->send(
			$data['booking']['customer_email'],
			sprintf(
				/* translators: %s: site name */
				__( '[%s] Your booking has been rescheduled', 'wp-appointments' ),
				$data['site_name']
			),
			'reschedule-customer',
			$data
		);
	}

	/** Sent to admin after a customer reschedules. */
	public function send_reschedule_admin( int $booking_id ): bool {
		$data = $this->load_booking_data( $booking_id );
		if ( null === $data ) {
			return false;
		}

		return $this->send(
			$data['admin_email'],
			sprintf(
				/* translators: %s: customer name */
				__( 'Booking rescheduled by %s', 'wp-appointments' ),
				$data['booking']['customer_name']
			),
			'reschedule-admin',
			$data
		);
	}

	/** Sent to the customer the day before (or N days before) their appointment. */
	public function send_reminder_customer( int $booking_id ): bool {
		$data = $this->load_booking_data( $booking_id );
		if ( null === $data ) {
			return false;
		}

		return $this->send(
			$data['booking']['customer_email'],
			sprintf(
				/* translators: 1: site name, 2: appointment date */
				__( '[%1$s] Reminder: your appointment on %2$s', 'wp-appointments' ),
				$data['site_name'],
				$data['booking']['appointment_date']
			),
			'reminder-customer',
			$data
		);
	}

	/** Admin-written follow-up message sent to the customer. */
	public function send_followup( int $booking_id, string $message, array $attachments = [] ): bool {
		$data = $this->load_booking_data( $booking_id );
		if ( null === $data ) {
			return false;
		}

		// Message is already sanitised (sanitize_textarea_field) by the controller.
		// Convert newlines to <br> for the HTML template.
		$data['message'] = nl2br( esc_html( $message ) );

		return $this->send(
			$data['booking']['customer_email'],
			sprintf(
				/* translators: %s: site name */
				__( 'A message from %s', 'wp-appointments' ),
				$data['site_name']
			),
			'followup',
			$data,
			$attachments
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Load common data for all email templates.
	 *
	 * @return array<string, mixed>|null  Null if the booking is not found.
	 */
	private function load_booking_data( int $booking_id ): ?array {
		$booking = $this->booking_model->find( $booking_id );

		if ( ! $booking ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "WPAPPT Email: booking #{$booking_id} not found — email not sent." );
			return null;
		}

		$service = $this->service_model->find( (int) $booking['service_id'] );

		return [
			'booking'         => $booking,
			'service'         => $service,
			'site_name'       => get_bloginfo( 'name' ),
			'admin_email'     => get_option( 'wpappt_admin_email', get_option( 'admin_email' ) ),
			'admin_panel_url' => admin_url( 'admin.php?page=wpappt-bookings&action=view&id=' . $booking_id ),
			'booking_page_url'=> $this->get_booking_page_url(),
			'reschedule_link' => '',
		];
	}

	/**
	 * Render template, wrap in base shell, send via wp_mail.
	 *
	 * @param array<string, mixed> $data
	 * @param string[]             $attachments Server file paths passed to wp_mail as the 5th argument.
	 */
	private function send( string $to, string $subject, string $template, array $data, array $attachments = [] ): bool {
		$html = $this->render_with_base( $template, $data );

		if ( '' === $html ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "WPAPPT Email: template '{$template}' rendered empty — email not sent." );
			return false;
		}

		return (bool) wp_mail( $to, $subject, $html, $this->get_headers(), $attachments );
	}

	/**
	 * Build the From and Content-Type headers.
	 *
	 * Customer name is never in a header here, but sender name comes from
	 * a WP option which could be changed by an admin. Sanitize with
	 * email_header_value() to strip any injected newlines.
	 *
	 * @return string[]
	 */
	private function get_headers(): array {
		$sender_name  = WPAPPT_Helper_Sanitizer::email_header_value(
			(string) get_option( 'wpappt_sender_name', get_bloginfo( 'name' ) )
		);
		$sender_email = (string) get_option( 'wpappt_admin_email', get_option( 'admin_email' ) );

		return [
			"From: {$sender_name} <{$sender_email}>",
			'Content-Type: text/html; charset=UTF-8',
		];
	}

	/**
	 * Render a specific template file and wrap it in base.php.
	 */
	private function render_with_base( string $template, array $data ): string {
		$content = $this->render( $template, $data );

		if ( '' === $content ) {
			return '';
		}

		return $this->render( 'base', array_merge( $data, [ 'content' => $content ] ) );
	}

	/**
	 * Render a single template file to a string.
	 *
	 * Template path is always resolved against the plugin's templates/emails/
	 * directory — user input never influences the file path.
	 */
	private function render( string $template, array $data ): string {
		$file = WPAPPT_PLUGIN_DIR . 'templates/emails/' . $template . '.php';

		if ( ! file_exists( $file ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "WPAPPT Email: template file not found: {$file}" );
			return '';
		}

		ob_start();
		// EXTR_SKIP prevents $file and other locals from being overwritten.
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		include $file;
		return (string) ob_get_clean();
	}

	private function get_booking_page_url(): string {
		$page_id = (int) get_option( 'wpappt_booking_page', 0 );
		return $page_id > 0 ? (string) get_permalink( $page_id ) : (string) home_url();
	}
}
