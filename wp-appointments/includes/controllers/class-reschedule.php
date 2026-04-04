<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles GET and POST /wpappt/v1/reschedule.
 *
 * GET  — validate a reschedule token and return the booking summary so the
 *        frontend can pre-populate the rescheduling form.
 * POST — apply a reschedule: validate token, verify the new slot is free,
 *        update the booking, consume the token (issue a fresh one), and
 *        notify customer + admin via action hooks.
 *
 * Both methods are rate-limited to 10 requests / hour / IP to deter
 * token-enumeration and brute-force attempts.
 */
class WPAPPT_Controller_Reschedule {

	private WPAPPT_Service_Token        $token_service;
	private WPAPPT_Model_Booking        $booking_model;
	private WPAPPT_Model_Service        $service_model;

	public function __construct(
		?WPAPPT_Service_Token   $token_service  = null,
		?WPAPPT_Model_Booking   $booking_model  = null,
		?WPAPPT_Model_Service   $service_model  = null
	) {
		$this->token_service  = $token_service  ?? new WPAPPT_Service_Token();
		$this->booking_model  = $booking_model  ?? new WPAPPT_Model_Booking();
		$this->service_model  = $service_model  ?? new WPAPPT_Model_Service();
	}

	// =========================================================================
	// GET /wpappt/v1/reschedule
	// =========================================================================

	/**
	 * WP REST API callback for GET /wpappt/v1/reschedule.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle_get( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! WPAPPT_Helper_Rate_Limiter::check( 'reschedule_get', $this->get_client_ip(), 10 ) ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'Too many requests. Please try again later.', 'wp-appointments' ) ],
				429
			);
		}

		$raw_token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$booking   = $this->token_service->validate( $raw_token );

		if ( null === $booking ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'This reschedule link is invalid or has expired.', 'wp-appointments' ) ],
				404
			);
		}

		$service = $this->service_model->find( (int) $booking['service_id'] );

		return new \WP_REST_Response( $this->format_booking( $booking, $service ), 200 );
	}

	// =========================================================================
	// POST /wpappt/v1/reschedule
	// =========================================================================

	/**
	 * WP REST API callback for POST /wpappt/v1/reschedule.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle_post( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->process_reschedule( [
			'token'            => $request->get_param( 'token' ),
			'appointment_date' => $request->get_param( 'appointment_date' ),
			'start_time'       => $request->get_param( 'start_time' ),
		] );
	}

	/**
	 * Core reschedule logic — separated from the REST layer for testability.
	 *
	 * @param  array<string, mixed> $params
	 * @return \WP_REST_Response
	 */
	public function process_reschedule( array $params ): \WP_REST_Response {
		// --- 1. Rate limit ---------------------------------------------------
		if ( ! WPAPPT_Helper_Rate_Limiter::check( 'reschedule_post', $this->get_client_ip(), 10 ) ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'Too many requests. Please try again later.', 'wp-appointments' ) ],
				429
			);
		}

		// --- 2. Token validation ---------------------------------------------
		$raw_token = sanitize_text_field( (string) ( $params['token'] ?? '' ) );
		$booking   = $this->token_service->validate( $raw_token );

		if ( null === $booking ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'This reschedule link is invalid or has expired.', 'wp-appointments' ) ],
				404
			);
		}

		// --- 3. Input sanitise + validate ------------------------------------
		$new_date       = sanitize_text_field( (string) ( $params['appointment_date'] ?? '' ) );
		$new_start_time = sanitize_text_field( (string) ( $params['start_time'] ?? '' ) );

		if ( ! $this->is_valid_future_date( $new_date ) ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'Please provide a valid future date (YYYY-MM-DD).', 'wp-appointments' ) ],
				422
			);
		}

		if ( ! preg_match( '/^\d{2}:\d{2}$/', $new_start_time ) ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'Please provide a valid time (HH:MM).', 'wp-appointments' ) ],
				422
			);
		}

		// --- 4. Service + end-time -------------------------------------------
		$booking_id = (int) $booking['id'];
		$service_id = (int) $booking['service_id'];
		$service    = $this->service_model->find( $service_id );

		if ( ! $service ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'The service for this booking was not found.', 'wp-appointments' ) ],
				422
			);
		}

		$duration     = (int) $service['duration_mins'];
		$new_end_time = ( new \DateTime( $new_start_time ) )
			->modify( "+{$duration} minutes" )
			->format( 'H:i' );

		// --- 5. Overlap check ------------------------------------------------
		// exclude_id = booking_id so the customer can re-select the same slot
		// without falsely triggering a conflict against their own current booking.
		$conflicts = $this->booking_model->count_overlapping(
			$new_date,
			$new_start_time . ':00',
			$new_end_time . ':00',
			$booking_id
		);

		if ( $conflicts > 0 ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'That time slot is no longer available. Please choose another.', 'wp-appointments' ) ],
				409
			);
		}

		// --- 6. Persist date/time change -------------------------------------
		$updated = $this->booking_model->update( $booking_id, [
			'appointment_date' => $new_date,
			'start_time'       => $new_start_time . ':00',
			'end_time'         => $new_end_time . ':00',
		] );

		if ( ! $updated ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'Could not save the new appointment time. Please try again.', 'wp-appointments' ) ],
				500
			);
		}

		// --- 7. Reset status to pending so admin reviews the change ----------
		$this->booking_model->update_status( $booking_id, 'pending', 'customer' );

		// --- 8. Rotate token -------------------------------------------------
		// Consume the just-used token and issue a fresh one for the next
		// potential reschedule. The new raw link goes into the confirmation email.
		$new_raw_token        = $this->token_service->consume( $booking_id );
		$new_reschedule_link  = $this->token_service->build_link( $new_raw_token );

		// --- 9. Notify -------------------------------------------------------
		do_action( 'wpappt_booking_rescheduled', $booking_id, $new_reschedule_link );

		return new \WP_REST_Response(
			[ 'message' => __( 'Your appointment has been rescheduled successfully.', 'wp-appointments' ) ],
			200
		);
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Validate that $date is a real calendar date that is today or in the future.
	 */
	private function is_valid_future_date( string $date ): bool {
		$dt = \DateTime::createFromFormat( 'Y-m-d', $date );

		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $date ) {
			return false;
		}

		return $dt >= new \DateTime( 'today' );
	}

	/**
	 * Shape the booking row into the subset of fields the frontend needs.
	 *
	 * Token hash, admin notes, and other sensitive fields are deliberately
	 * excluded from the public API response.
	 *
	 * @param array<string, mixed>      $booking
	 * @param array<string, mixed>|null $service
	 * @return array<string, mixed>
	 */
	private function format_booking( array $booking, ?array $service ): array {
		return [
			'id'               => (int) $booking['id'],
			'service_name'     => $service ? (string) $service['name'] : '',
			'duration_mins'    => $service ? (int) $service['duration_mins'] : 0,
			'appointment_date' => (string) $booking['appointment_date'],
			'start_time'       => substr( (string) $booking['start_time'], 0, 5 ),
			'end_time'         => substr( (string) $booking['end_time'],   0, 5 ),
			'customer_name'    => (string) $booking['customer_name'],
			'status'           => (string) $booking['status'],
		];
	}

	/**
	 * Determine the client IP for rate limiting.
	 *
	 * Checks X-Forwarded-For first (proxy/load-balancer environments) and
	 * falls back to REMOTE_ADDR. Used only for rate-limiting, not for any
	 * security-critical purpose.
	 */
	public function get_client_ip(): string {
		$forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

		if ( $forwarded ) {
			$ip = trim( explode( ',', $forwarded )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}
}
