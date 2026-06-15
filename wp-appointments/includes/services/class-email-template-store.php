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
	 * Each field value is an array keyed by language code ('en', 'nl') containing the default text.
	 *
	 * @return array<string, array{label: string, customer_facing?: bool, fields: array<string, array<string, string>>}> The map of template slug to config
	 */
	public static function get_registry(): array {
		return [
			'booking_received_customer' => [
				'label'           => 'Booking Received (Customer)',
				'customer_facing' => true,
				'fields'          => [
					'subject' => [
						'en' => '[{{site_name}}] Booking request received',
						'nl' => '[{{site_name}}] Boekingsverzoek ontvangen',
					],
					'body'    => [
						'en' => "Thank you — we have received your booking request. We will review it and confirm your appointment shortly.\n\nYou will receive another email once your appointment is confirmed.",
						'nl' => "Bedankt — we hebben uw boekingsverzoek ontvangen. We zullen het beoordelen en uw afspraak binnenkort bevestigen.\n\nU ontvangt een tweede e-mail zodra uw afspraak is bevestigd.",
					],
					'closing' => [
						'en' => 'If you did not make this booking, you can safely ignore this email.',
						'nl' => 'Als u deze boeking niet heeft gemaakt, kunt u deze e-mail veilig negeren.',
					],
				],
			],
			'booking_confirmed' => [
				'label'           => 'Booking Confirmed',
				'customer_facing' => true,
				'fields'          => [
					'subject' => [
						'en' => '[{{site_name}}] Your booking is confirmed',
						'nl' => '[{{site_name}}] Uw boeking is bevestigd',
					],
					'body'    => [
						'en' => 'Great news — your appointment has been confirmed. We look forward to seeing you!',
						'nl' => 'Goed nieuws — uw afspraak is bevestigd. We kijken ernaar uit u te zien!',
					],
					'closing' => [
						'en' => 'If you have any questions, simply reply to this email.',
						'nl' => 'Als u vragen heeft, kunt u gewoon op deze e-mail reageren.',
					],
				],
			],
			'booking_cancelled' => [
				'label'           => 'Booking Cancelled',
				'customer_facing' => true,
				'fields'          => [
					'subject' => [
						'en' => '[{{site_name}}] Your booking has been cancelled',
						'nl' => '[{{site_name}}] Uw boeking is geannuleerd',
					],
					'body'    => [
						'en' => 'We are sorry to let you know that the following booking has been cancelled.',
						'nl' => 'We moeten u helaas laten weten dat de volgende boeking is geannuleerd.',
					],
					'closing' => [
						'en' => 'If you have any questions, simply reply to this email.',
						'nl' => 'Als u vragen heeft, kunt u gewoon op deze e-mail reageren.',
					],
				],
			],
			'reschedule_customer' => [
				'label'           => 'Rescheduled (Customer)',
				'customer_facing' => true,
				'fields'          => [
					'subject' => [
						'en' => '[{{site_name}}] Your booking has been rescheduled',
						'nl' => '[{{site_name}}] Uw boeking is verzet',
					],
					'body'    => [
						'en' => 'Your booking has been rescheduled. Here are your updated appointment details:',
						'nl' => 'Uw boeking is verzet. Hier zijn uw bijgewerkte afspraakgegevens:',
					],
					'closing' => [
						'en' => 'If you have any questions, simply reply to this email.',
						'nl' => 'Als u vragen heeft, kunt u gewoon op deze e-mail reageren.',
					],
				],
			],
			'reminder_customer' => [
				'label'           => 'Appointment Reminder',
				'customer_facing' => true,
				'fields'          => [
					'subject' => [
						'en' => '[{{site_name}}] Reminder: your appointment on {{appointment_date}}',
						'nl' => '[{{site_name}}] Herinnering: uw afspraak op {{appointment_date}}',
					],
					'body'    => [
						'en' => 'This is a friendly reminder about your upcoming appointment.',
						'nl' => 'Dit is een vriendelijke herinnering aan uw aankomende afspraak.',
					],
					'closing' => [
						'en' => 'If you have any questions, simply reply to this email.',
						'nl' => 'Als u vragen heeft, kunt u gewoon op deze e-mail reageren.',
					],
				],
			],
			'followup' => [
				'label'           => 'Follow-up Message',
				'customer_facing' => true,
				'fields'          => [
					'subject' => [
						'en' => 'A message from {{site_name}}',
						'nl' => 'Een bericht van {{site_name}}',
					],
					'body'    => [
						'en' => 'You have a message from {{site_name}} regarding your appointment:',
						'nl' => 'U heeft een bericht van {{site_name}} over uw afspraak:',
					],
					'closing' => [
						'en' => 'To reply, simply respond to this email.',
						'nl' => 'Om te antwoorden, reageert u gewoon op deze e-mail.',
					],
				],
			],
			'booking_received_admin' => [
				'label'  => 'Booking Received (Admin)',
				'fields' => [
					'subject' => [
						'en' => 'New booking request from {{customer_name}}',
						'nl' => 'Nieuw boekingsverzoek van {{customer_name}}',
					],
					'body'    => [
						'en' => 'A new booking request has been submitted and is waiting for your confirmation.',
						'nl' => 'Er is een nieuw boekingsverzoek ingediend dat wacht op uw bevestiging.',
					],
				],
			],
			'reschedule_admin' => [
				'label'  => 'Rescheduled (Admin)',
				'fields' => [
					'subject' => [
						'en' => 'Booking rescheduled by {{customer_name}}',
						'nl' => 'Boeking verzet door {{customer_name}}',
					],
					'body'    => [
						'en' => '{{customer_name}} has rescheduled their appointment. The booking status has been reset to Pending.',
						'nl' => '{{customer_name}} heeft de afspraak verzet. De boekingsstatus is teruggezet naar In afwachting.',
					],
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
		foreach ( $registry[ $slug ]['fields'] as $field => $defaults ) {
			$stored = (string) get_option( "wpappt_tpl_{$slug}_{$lang}_{$field}", '' );
			$result[ $field ] = $stored !== '' ? $stored : $defaults[ $lang ];
		}
		return $result;
	}

	/**
	 * Return the server file path of the default attachment for a template+language.
	 *
	 * Returns '' if no attachment is configured, the WP attachment is invalid,
	 * or the file does not exist on disk.
	 *
	 * @param string $slug Template slug from the registry.
	 * @param string $lang Language code, must be 'en' or 'nl'.
	 * @return string Absolute file path, or '' when the attachment is unavailable.
	 */
	public static function get_default_attachment_path( string $slug, string $lang ): string {
		$id = (int) get_option( "wpappt_tpl_{$slug}_{$lang}_attachment_id", 0 );

		if ( $id <= 0 ) {
			return '';
		}

		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return '';
		}

		$path = get_attached_file( $id );

		if ( ! $path || ! file_exists( $path ) ) {
			return '';
		}

		return $path;
	}
}
