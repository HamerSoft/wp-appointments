<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers all plugin REST API routes.
 *
 * Routes:
 *   GET  /wpappt/v1/services              — list active services
 *   GET  /wpappt/v1/availability          — available time slots for a date + service
 *   POST /wpappt/v1/bookings              — create a booking (nonce-protected)
 *   GET  /wpappt/v1/reschedule            — validate token, return booking (Step 7)
 *   POST /wpappt/v1/reschedule            — submit reschedule (Step 7)
 */
class WPAPPT_Rest_Api {

	const NAMESPACE = 'wpappt/v1';

	public function register_routes(): void {
		$booking_controller = new WPAPPT_Controller_Booking();

		// -----------------------------------------------------------------
		// GET /wpappt/v1/services
		// -----------------------------------------------------------------
		register_rest_route( self::NAMESPACE, '/services', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_services' ],
			'permission_callback' => '__return_true',
		] );

		// -----------------------------------------------------------------
		// GET /wpappt/v1/availability
		// -----------------------------------------------------------------
		register_rest_route( self::NAMESPACE, '/availability', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_availability' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'service_id' => [
					'required'          => true,
					'type'              => 'integer',
					'minimum'           => 1,
					'sanitize_callback' => 'absint',
				],
				'date'       => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => static function ( string $value ): bool {
						$dt = \DateTime::createFromFormat( 'Y-m-d', $value );
						return $dt && $dt->format( 'Y-m-d' ) === $value;
					},
				],
			],
		] );

		// -----------------------------------------------------------------
		// POST /wpappt/v1/bookings
		// Nonce (X-WP-Nonce) validated automatically by WP REST middleware.
		// -----------------------------------------------------------------
		register_rest_route( self::NAMESPACE, '/bookings', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $booking_controller, 'handle_create' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'service_id'       => [ 'required' => true,  'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ],
				'appointment_date' => [ 'required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
				'start_time'       => [ 'required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
				'customer_name'    => [ 'required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
				'customer_email'   => [ 'required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_email' ],
				'customer_phone'   => [ 'required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
				'injury_notes'     => [ 'required' => false, 'type' => 'string',  'sanitize_callback' => 'wp_kses_post', 'default' => '' ],
				'comments'         => [ 'required' => false, 'type' => 'string',  'sanitize_callback' => 'wp_kses_post', 'default' => '' ],
			],
		] );

		// Reschedule routes are registered in Step 7 (WPAPPT_Controller_Reschedule).
	}

	// =========================================================================
	// Endpoint callbacks
	// =========================================================================

	/**
	 * GET /wpappt/v1/services
	 *
	 * @return \WP_REST_Response
	 */
	public function get_services(): \WP_REST_Response {
		$service_model = new WPAPPT_Model_Service();

		$services = array_map(
			static fn( array $s ): array => [
				'id'            => (int) $s['id'],
				'name'          => $s['name'],
				'duration_mins' => (int) $s['duration_mins'],
				'price'         => (float) $s['price'],
			],
			$service_model->find_all_active()
		);

		return new \WP_REST_Response( $services, 200 );
	}

	/**
	 * GET /wpappt/v1/availability?service_id=&date=
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function get_availability( \WP_REST_Request $request ): \WP_REST_Response {
		$service_id = (int) $request->get_param( 'service_id' );
		$date       = (string) $request->get_param( 'date' );

		$slots = ( new WPAPPT_Service_Availability() )->get_available_slots( $date, $service_id );

		return new \WP_REST_Response( $slots, 200 );
	}
}
