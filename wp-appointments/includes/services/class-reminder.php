<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drives the daily reminder cron job.
 *
 * Queries confirmed bookings due for a reminder, fires an action per booking
 * so the email service (and any future listeners) can react, then stamps
 * reminder_sent_at to prevent duplicate sends.
 */
class WPAPPT_Service_Reminder {

	private WPAPPT_Model_Booking $booking_model;

	public function __construct( ?WPAPPT_Model_Booking $booking_model = null ) {
		$this->booking_model = $booking_model ?? new WPAPPT_Model_Booking();
	}

	public function init(): void {
		add_action( 'wpappt_send_reminders', [ $this, 'run' ] );
	}

	/**
	 * Called by WP-Cron daily.
	 *
	 * Skips silently when reminders are disabled in settings.
	 */
	public function run(): void {
		if ( ! (int) get_option( 'wpappt_reminders_enabled', 1 ) ) {
			return;
		}

		$days     = max( 1, (int) get_option( 'wpappt_reminder_days', 1 ) );
		$bookings = $this->booking_model->find_due_for_reminder( $days );

		foreach ( $bookings as $booking ) {
			$id = (int) $booking['id'];

			/**
			 * Fires once per booking that is due for a reminder.
			 *
			 * @param int $id Booking ID.
			 */
			do_action( 'wpappt_booking_reminder', $id );

			// Stamp after firing so a failed email send does not permanently
			// suppress retries — but stamp immediately to avoid double-sending
			// if the cron fires twice within the same day.
			$this->booking_model->mark_reminder_sent( $id );
		}
	}
}
