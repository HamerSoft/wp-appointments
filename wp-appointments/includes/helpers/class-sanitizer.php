<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralised input sanitisation helpers.
 *
 * Each method returns a clean array with only the expected keys present.
 * Validation (is the value acceptable?) is left to the caller — this layer
 * only ensures data is safe to store and display.
 */
class WPAPPT_Helper_Sanitizer {

	/**
	 * Sanitise booking form submission (REST API or reschedule POST).
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	public static function booking_input( array $raw ): array {
		return [
			'service_id'       => (int) ( $raw['service_id'] ?? 0 ),
			'appointment_date' => sanitize_text_field( $raw['appointment_date'] ?? '' ),
			'start_time'       => sanitize_text_field( $raw['start_time']       ?? '' ),
			'customer_name'    => sanitize_text_field( $raw['customer_name']    ?? '' ),
			'customer_email'   => sanitize_email( $raw['customer_email']        ?? '' ),
			'customer_phone'   => sanitize_text_field( $raw['customer_phone']   ?? '' ),
			'injury_notes'     => sanitize_textarea_field( $raw['injury_notes'] ?? '' ),
			'comments'         => sanitize_textarea_field( $raw['comments']     ?? '' ),
		];
	}

	/**
	 * Sanitise service form submission (admin).
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	public static function service_input( array $raw ): array {
		return [
			'name'          => sanitize_text_field( $raw['name']      ?? '' ),
			'duration_mins' => (int)   ( $raw['duration_mins']        ?? 0  ),
			'price'         => round( (float) ( $raw['price']         ?? 0  ), 2 ),
			'is_active'     => isset( $raw['is_active'] ) ? 1 : 0,
			'sort_order'    => (int)   ( $raw['sort_order']           ?? 0  ),
		];
	}

	/**
	 * Sanitise a single availability slot (admin).
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	public static function availability_slot( array $raw ): array {
		return [
			'day_of_week'  => (int)  ( $raw['day_of_week']  ?? 0 ),
			'start_time'   => sanitize_text_field( $raw['start_time'] ?? '' ),
			'end_time'     => sanitize_text_field( $raw['end_time']   ?? '' ),
			'is_available' => isset( $raw['is_available'] ) ? 1 : 0,
			'label'        => sanitize_text_field( $raw['label']      ?? '' ) ?: null,
		];
	}

	/**
	 * Sanitise a blocked slot form submission (admin).
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	public static function blocked_slot_input( array $raw ): array {
		return [
			'blocked_date' => sanitize_text_field( $raw['blocked_date'] ?? '' ),
			'start_time'   => sanitize_text_field( $raw['start_time']   ?? '' ),
			'end_time'     => sanitize_text_field( $raw['end_time']      ?? '' ),
			'reason'       => sanitize_text_field( $raw['reason']        ?? '' ) ?: null,
		];
	}

	/**
	 * Sanitise the follow-up email message (admin, plain text only).
	 */
	public static function followup_message( string $raw ): string {
		return sanitize_textarea_field( $raw );
	}

	/**
	 * Sanitise admin notes on a booking (admin, plain text only).
	 */
	public static function admin_notes( string $raw ): string {
		return sanitize_textarea_field( $raw );
	}

	/**
	 * Strip newlines from a string used in an email header value.
	 *
	 * Prevents header injection when customer-supplied data (e.g. name) is
	 * interpolated into From / Reply-To headers.
	 */
	public static function email_header_value( string $value ): string {
		return str_replace( [ "\r", "\n" ], '', $value );
	}
}
