<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of email template defaults and admin-stored overrides.
 */
class WPAPPT_Service_Email_Template_Store {

	/**
	 * Returns the email template registry.
	 *
	 * @return array<string, array{label: string, fields: array<string, string>}> The map of template slug to config
	 */
	public static function get_registry(): array {
		return [
			'booking_received_customer' => [
				'label'  => 'Booking Received (Customer)',
				'fields' => [
					'subject' => '[{{site_name}}] Booking request received',
					'body'    => "Thank you — we have received your booking request. We will review it and confirm your appointment shortly.\n\nYou will receive another email once your appointment is confirmed.",
					'closing' => 'If you did not make this booking, you can safely ignore this email.',
				],
			],
			'booking_confirmed' => [
				'label'  => 'Booking Confirmed',
				'fields' => [
					'subject' => '[{{site_name}}] Your booking is confirmed',
					'body'    => 'Great news — your appointment has been confirmed. We look forward to seeing you!',
					'closing' => 'If you have any questions, simply reply to this email.',
				],
			],
			'booking_cancelled' => [
				'label'  => 'Booking Cancelled',
				'fields' => [
					'subject' => '[{{site_name}}] Your booking has been cancelled',
					'body'    => 'We are sorry to let you know that the following booking has been cancelled.',
					'closing' => 'If you have any questions, simply reply to this email.',
				],
			],
			'reschedule_customer' => [
				'label'  => 'Rescheduled (Customer)',
				'fields' => [
					'subject' => '[{{site_name}}] Your booking has been rescheduled',
					'body'    => 'Your booking has been rescheduled. Here are your updated appointment details:',
					'closing' => 'If you have any questions, simply reply to this email.',
				],
			],
			'reminder_customer' => [
				'label'  => 'Appointment Reminder',
				'fields' => [
					'subject' => '[{{site_name}}] Reminder: your appointment on {{appointment_date}}',
					'body'    => 'This is a friendly reminder about your upcoming appointment.',
					'closing' => 'If you have any questions, simply reply to this email.',
				],
			],
			'followup' => [
				'label'  => 'Follow-up Message',
				'fields' => [
					'subject' => 'A message from {{site_name}}',
					'body'    => 'You have a message from {{site_name}} regarding your appointment:',
					'closing' => 'To reply, simply respond to this email.',
				],
			],
			'booking_received_admin' => [
				'label'  => 'Booking Received (Admin)',
				'fields' => [
					'subject' => 'New booking request from {{customer_name}}',
					'body'    => 'A new booking request has been submitted and is waiting for your confirmation.',
				],
			],
			'reschedule_admin' => [
				'label'  => 'Rescheduled (Admin)',
				'fields' => [
					'subject' => 'Booking rescheduled by {{customer_name}}',
					'body'    => '{{customer_name}} has rescheduled their appointment. The booking status has been reset to Pending.',
				],
			],
		];
	}

	/**
	 * Resolves the email template text for a given slug and language.
	 *
	 * @param string $slug Template slug from the registry.
	 * @param string $lang Language code, must be 'en' or 'nl'.
	 * @return array<string, string> Field-name to resolved text, or empty array for unknown slug or invalid lang
	 */
	public static function get_texts( string $slug, string $lang ): array {
		$registry = self::get_registry();
		if ( ! isset( $registry[ $slug ] ) ) {
			return [];
		}

		if ( ! in_array( $lang, [ 'en', 'nl' ], true ) ) {
			return [];
		}

		$result = [];
		foreach ( $registry[ $slug ]['fields'] as $field => $default ) {
			$stored = (string) get_option( "wpappt_tpl_{$slug}_{$lang}_{$field}", '' );
			$result[ $field ] = $stored !== '' ? $stored : $default;
		}
		return $result;
	}
}
