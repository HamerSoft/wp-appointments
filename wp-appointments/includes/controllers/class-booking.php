<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles POST /wpappt/v1/bookings.
 *
 * Responsibility chain:
 *   1. Rate-limit check (transient, per IP).
 *   2. Input sanitisation via WPAPPT_Helper_Sanitizer.
 *   3. Required-field and format validation.
 *   4. Slot availability check (uses the availability service, same logic as GET /availability).
 *   5. Booking creation.
 *   6. Action hook so the email service (Step 6) can send notifications.
 */
class WPAPPT_Controller_Booking {

	private WPAPPT_Model_Service        $service_model;
	private WPAPPT_Model_Booking        $booking_model;
	private WPAPPT_Service_Availability $availability_service;

	public function __construct(
		?WPAPPT_Model_Service        $service_model        = null,
		?WPAPPT_Model_Booking        $booking_model        = null,
		?WPAPPT_Service_Availability $availability_service = null
	) {
		$this->service_model        = $service_model        ?? new WPAPPT_Model_Service();
		$this->booking_model        = $booking_model        ?? new WPAPPT_Model_Booking();
		$this->availability_service = $availability_service ?? new WPAPPT_Service_Availability();
	}

	// =========================================================================
	// REST handler
	// =========================================================================

	/**
	 * WP REST API callback for POST /wpappt/v1/bookings.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_create( \WP_REST_Request $request ) {
		$result = $this->process_create( $request->get_params() );

		if ( $result instanceof \WP_Error ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 201 );
	}

	/**
	 * Core booking creation logic — separated from the REST layer for testability.
	 *
	 * @param  array<string, mixed> $params Raw request parameters.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function process_create( array $params ) {
		// --- 1. Rate limit ---------------------------------------------------
		if ( ! WPAPPT_Helper_Rate_Limiter::check( 'booking', $this->get_client_ip(), 5 ) ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Too many booking attempts. Please wait before trying again.', 'wp-appointments' ),
				[ 'status' => 429 ]
			);
		}

		// --- 2. Sanitise -----------------------------------------------------
		$data = WPAPPT_Helper_Sanitizer::booking_input( $params );

		// --- 3. Validate -----------------------------------------------------
		$error = $this->validate( $data );
		if ( $error instanceof \WP_Error ) {
			return $error;
		}

		// --- 4. Slot availability (pre-check, no lock) -----------------------
		$available = $this->availability_service->get_available_slots(
			$data['appointment_date'],
			$data['service_id']
		);

		$requested_start  = substr( $data['start_time'], 0, 5 ); // normalise to H:i
		$available_starts = array_column( $available, 'start_time' );

		if ( ! in_array( $requested_start, $available_starts, true ) ) {
			return new \WP_Error(
				'slot_unavailable',
				__( 'This time slot is no longer available. Please choose another.', 'wp-appointments' ),
				[ 'status' => 409 ]
			);
		}

		// --- 5. Calculate end_time (needed before the lock query) ------------
		$service          = $this->service_model->find( $data['service_id'] );
		$data['end_time'] = $this->calculate_end_time(
			$data['start_time'],
			(int) $service['duration_mins']
		);

		// --- 6. Transactional double-check + insert --------------------------
		// Lock overlapping rows with FOR UPDATE so a second concurrent request
		// for the same slot blocks until this transaction commits or rolls back,
		// closing the race window between the pre-check above and the insert.
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		$overlap = $this->booking_model->count_overlapping_for_update(
			$data['appointment_date'],
			$data['start_time'],
			$data['end_time']
		);

		if ( $overlap > 0 ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error(
				'slot_taken',
				__( 'This time slot was just taken. Please go back and choose another time.', 'wp-appointments' ),
				[ 'status' => 409 ]
			);
		}

		$booking_id = $this->booking_model->create( $data );

		if ( ! $booking_id ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error(
				'booking_failed',
				__( 'Could not save your booking. Please try again.', 'wp-appointments' ),
				[ 'status' => 500 ]
			);
		}

		$wpdb->query( 'COMMIT' );

		// --- 7. Notify -------------------------------------------------------
		// Email service (Step 6) hooks into this action.
		do_action( 'wpappt_booking_created', $booking_id );

		return [
			'booking_id' => $booking_id,
			'message'    => __( 'Your booking request has been received. We will confirm it shortly.', 'wp-appointments' ),
		];
	}

	// =========================================================================
	// Validation
	// =========================================================================

	/**
	 * @param  array<string, mixed> $data Sanitised booking input.
	 * @return \WP_Error|null
	 */
	private function validate( array $data ): ?\WP_Error {
		// Required text fields.
		if ( '' === $data['customer_name'] ) {
			return new \WP_Error(
				'missing_field',
				__( 'Customer name is required.', 'wp-appointments' ),
				[ 'status' => 422 ]
			);
		}

		// Phone: required and must contain only digits, spaces, +, -, dots, parens; 7–15 digits.
		if ( '' === $data['customer_phone'] ) {
			return new \WP_Error(
				'missing_field',
				__( 'Phone number is required.', 'wp-appointments' ),
				[ 'status' => 422 ]
			);
		}
		$digits_only = preg_replace( '/\D/', '', $data['customer_phone'] );
		if ( ! preg_match( '/^[+]?[\d\s\-().]+$/', $data['customer_phone'] )
			|| strlen( $digits_only ) < 7
			|| strlen( $digits_only ) > 15
		) {
			return new \WP_Error(
				'invalid_phone',
				__( 'Please provide a valid phone number.', 'wp-appointments' ),
				[ 'status' => 422 ]
			);
		}

		// Email.
		if ( '' === $data['customer_email'] || ! is_email( $data['customer_email'] ) ) {
			return new \WP_Error(
				'invalid_email',
				__( 'A valid email address is required.', 'wp-appointments' ),
				[ 'status' => 422 ]
			);
		}

		// Service ID.
		if ( $data['service_id'] < 1 ) {
			return new \WP_Error(
				'invalid_service',
				__( 'Please select a service.', 'wp-appointments' ),
				[ 'status' => 422 ]
			);
		}

		// Date: valid calendar date and not in the past.
		$dt = \DateTime::createFromFormat( 'Y-m-d', $data['appointment_date'] );
		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $data['appointment_date'] ) {
			return new \WP_Error(
				'invalid_date',
				__( 'Please provide a valid date.', 'wp-appointments' ),
				[ 'status' => 422 ]
			);
		}
		if ( $dt < new \DateTime( 'today' ) ) {
			return new \WP_Error(
				'past_date',
				__( 'Appointments cannot be booked in the past.', 'wp-appointments' ),
				[ 'status' => 422 ]
			);
		}

		// Start time: H:i or H:i:s.
		if ( ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $data['start_time'] ) ) {
			return new \WP_Error(
				'invalid_time',
				__( 'Please provide a valid start time.', 'wp-appointments' ),
				[ 'status' => 422 ]
			);
		}

		return null;
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	private function calculate_end_time( string $start_time, int $duration_mins ): string {
		return ( new \DateTime( $start_time ) )
			->modify( "+{$duration_mins} minutes" )
			->format( 'H:i:s' );
	}

	/**
	 * Resolve the client IP from server vars.
	 *
	 * We check X-Forwarded-For first (common behind a proxy/CDN) but fall
	 * back to REMOTE_ADDR. This is used only for rate-limiting (bot deterrence),
	 * not for any security-critical purpose.
	 */
	public function get_client_ip(): string {
		$forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

		if ( $forwarded ) {
			// Proxy chains are comma-separated; the leftmost is the client.
			$ip = trim( explode( ',', $forwarded )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}
}
