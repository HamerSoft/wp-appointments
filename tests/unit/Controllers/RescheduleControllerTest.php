<?php

namespace WpAppointments\Tests\Unit\Controllers;

use Brain\Monkey\Functions;
use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Controller_Reschedule;
use WPAPPT_Service_Token;
use WPAPPT_Model_Booking;
use WPAPPT_Model_Service;

/**
 * @covers WPAPPT_Controller_Reschedule
 *
 * GET tests use handle_get() with a WP_REST_Request stub.
 * POST tests call process_reschedule() directly to stay independent of
 * the REST layer (same pattern as BookingControllerTest).
 */
class RescheduleControllerTest extends WpTestCase {

	// -------------------------------------------------------------------------
	// Fixtures
	// -------------------------------------------------------------------------

	private function makeController(
		?\Mockery\MockInterface $token_service  = null,
		?\Mockery\MockInterface $booking_model  = null,
		?\Mockery\MockInterface $service_model  = null
	): WPAPPT_Controller_Reschedule {
		return new WPAPPT_Controller_Reschedule(
			$token_service ?? \Mockery::mock( WPAPPT_Service_Token::class ),
			$booking_model ?? \Mockery::mock( WPAPPT_Model_Booking::class ),
			$service_model ?? \Mockery::mock( WPAPPT_Model_Service::class )
		);
	}

	private function fakeBooking( array $overrides = [] ): array {
		return array_merge( [
			'id'               => 1,
			'service_id'       => 2,
			'status'           => 'confirmed',
			'appointment_date' => '2026-07-01',
			'start_time'       => '10:00:00',
			'end_time'         => '11:00:00',
			'customer_name'    => 'Jane Doe',
			'customer_email'   => 'jane@example.com',
			'reschedule_token' => hash( 'sha256', 'valid-raw-token' ),
			'token_expires_at' => '2099-01-01 00:00:00',
		], $overrides );
	}

	private function fakeService(): array {
		return [ 'id' => 2, 'name' => 'Swedish Massage', 'duration_mins' => 60 ];
	}

	private function validPostParams( array $overrides = [] ): array {
		return array_merge( [
			'token'            => 'valid-raw-token',
			'appointment_date' => ( new \DateTime( '+7 days' ) )->format( 'Y-m-d' ),
			'start_time'       => '10:00',
		], $overrides );
	}

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'error_log' )->justReturn( null );
	}

	// =========================================================================
	// GET — rate limiting
	// =========================================================================

	/** @test */
	public function handle_get_returns_429_when_rate_limited(): void {
		Functions\when( 'get_transient' )->justReturn( [ 'count' => 10, 'since' => time() ] );

		$request = new \WP_REST_Request( [ 'token' => 'any' ] );
		$result  = $this->makeController()->handle_get( $request );

		$this->assertSame( 429, $result->get_status() );
	}

	// =========================================================================
	// GET — token validation
	// =========================================================================

	/** @test */
	public function handle_get_returns_404_for_invalid_token(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$token_service = \Mockery::mock( WPAPPT_Service_Token::class );
		$token_service->shouldReceive( 'validate' )->andReturn( null );

		$request = new \WP_REST_Request( [ 'token' => 'bad-token' ] );
		$result  = $this->makeController( $token_service )->handle_get( $request );

		$this->assertSame( 404, $result->get_status() );
	}

	/** @test */
	public function handle_get_returns_200_with_booking_summary_for_valid_token(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$token_service = \Mockery::mock( WPAPPT_Service_Token::class );
		$token_service->shouldReceive( 'validate' )->andReturn( $this->fakeBooking() );

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		$request = new \WP_REST_Request( [ 'token' => 'valid-raw-token' ] );
		$result  = $this->makeController( $token_service, null, $service_model )->handle_get( $request );

		$this->assertSame( 200, $result->get_status() );

		$data = $result->get_data();
		$this->assertSame( 1, $data['id'] );
		$this->assertSame( 'Jane Doe', $data['customer_name'] );
		$this->assertSame( 'Swedish Massage', $data['service_name'] );
		// Token hash must NOT be in the response.
		$this->assertArrayNotHasKey( 'reschedule_token', $data );
	}

	// =========================================================================
	// POST — rate limiting
	// =========================================================================

	/** @test */
	public function process_reschedule_returns_429_when_rate_limited(): void {
		Functions\when( 'get_transient' )->justReturn( [ 'count' => 10, 'since' => time() ] );

		$result = $this->makeController()->process_reschedule( $this->validPostParams() );

		$this->assertSame( 429, $result->get_status() );
	}

	// =========================================================================
	// POST — token validation
	// =========================================================================

	/** @test */
	public function process_reschedule_returns_404_for_invalid_token(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$token_service = \Mockery::mock( WPAPPT_Service_Token::class );
		$token_service->shouldReceive( 'validate' )->andReturn( null );

		$result = $this->makeController( $token_service )
		               ->process_reschedule( $this->validPostParams( [ 'token' => 'expired' ] ) );

		$this->assertSame( 404, $result->get_status() );
	}

	// =========================================================================
	// POST — input validation
	// =========================================================================

	/** @test */
	public function process_reschedule_returns_422_for_past_date(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$token_service = \Mockery::mock( WPAPPT_Service_Token::class );
		$token_service->shouldReceive( 'validate' )->andReturn( $this->fakeBooking() );

		$result = $this->makeController( $token_service )
		               ->process_reschedule( $this->validPostParams( [ 'appointment_date' => '2020-01-01' ] ) );

		$this->assertSame( 422, $result->get_status() );
	}

	/** @test */
	public function process_reschedule_returns_422_for_invalid_time_format(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$token_service = \Mockery::mock( WPAPPT_Service_Token::class );
		$token_service->shouldReceive( 'validate' )->andReturn( $this->fakeBooking() );

		$result = $this->makeController( $token_service )
		               ->process_reschedule( $this->validPostParams( [ 'start_time' => 'not-a-time' ] ) );

		$this->assertSame( 422, $result->get_status() );
	}

	// =========================================================================
	// POST — slot availability
	// =========================================================================

	/** @test */
	public function process_reschedule_returns_409_when_slot_is_taken(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$token_service = \Mockery::mock( WPAPPT_Service_Token::class );
		$token_service->shouldReceive( 'validate' )->andReturn( $this->fakeBooking() );

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'count_overlapping' )->andReturn( 1 );

		$result = $this->makeController( $token_service, $booking_model, $service_model )
		               ->process_reschedule( $this->validPostParams() );

		$this->assertSame( 409, $result->get_status() );
	}

	// =========================================================================
	// POST — happy path
	// =========================================================================

	/** @test */
	public function process_reschedule_returns_200_on_success(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'home_url' )->justReturn( 'http://example.com/' );
		Functions\when( 'add_query_arg' )->alias( function ( string $key, string $val, string $base ): string {
			return $base . '?' . $key . '=' . $val;
		} );

		$token_service = \Mockery::mock( WPAPPT_Service_Token::class );
		$token_service->shouldReceive( 'validate' )->andReturn( $this->fakeBooking() );
		$token_service->shouldReceive( 'consume' )->andReturn( str_repeat( 'c', 64 ) );
		$token_service->shouldReceive( 'build_link' )->andReturn( 'http://example.com/?reschedule=ccc' );

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'count_overlapping' )->andReturn( 0 );
		$booking_model->shouldReceive( 'update' )->andReturn( true );
		$booking_model->shouldReceive( 'update_status' )->andReturn( true );

		$result = $this->makeController( $token_service, $booking_model, $service_model )
		               ->process_reschedule( $this->validPostParams() );

		$this->assertSame( 200, $result->get_status() );
	}

	/** @test */
	public function process_reschedule_resets_booking_status_to_pending(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'add_query_arg' )->alias( function ( string $k, string $v, string $b ): string { return $b . '?' . $k . '=' . $v; } );
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'home_url' )->justReturn( 'http://example.com/' );

		$token_service = \Mockery::mock( WPAPPT_Service_Token::class );
		$token_service->shouldReceive( 'validate' )->andReturn( $this->fakeBooking() );
		$token_service->shouldReceive( 'consume' )->andReturn( str_repeat( 'd', 64 ) );
		$token_service->shouldReceive( 'build_link' )->andReturn( 'http://example.com/?reschedule=ddd' );

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'count_overlapping' )->andReturn( 0 );
		$booking_model->shouldReceive( 'update' )->andReturn( true );

		$booking_model->shouldReceive( 'update_status' )
		              ->once()
		              ->withArgs( function ( int $id, string $status, string $actor ): bool {
			              return $status === 'pending' && $actor === 'customer';
		              } )
		              ->andReturn( true );

		$this->makeController( $token_service, $booking_model, $service_model )
		     ->process_reschedule( $this->validPostParams() );
	}

	/** @test */
	public function process_reschedule_fires_booking_rescheduled_hook_with_new_link(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'add_query_arg' )->alias( function ( string $k, string $v, string $b ): string { return $b . '?' . $k . '=' . $v; } );
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'home_url' )->justReturn( 'http://example.com/' );

		$token_service = \Mockery::mock( WPAPPT_Service_Token::class );
		$token_service->shouldReceive( 'validate' )->andReturn( $this->fakeBooking() );
		$token_service->shouldReceive( 'consume' )->andReturn( str_repeat( 'e', 64 ) );
		$token_service->shouldReceive( 'build_link' )->andReturn( 'http://example.com/?reschedule=newlink' );

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'count_overlapping' )->andReturn( 0 );
		$booking_model->shouldReceive( 'update' )->andReturn( true );
		$booking_model->shouldReceive( 'update_status' )->andReturn( true );

		Functions\expect( 'do_action' )
			->once()
			->withArgs( function ( string $hook, int $id, string $link ): bool {
				return $hook === 'wpappt_booking_rescheduled'
				    && $id === 1
				    && $link === 'http://example.com/?reschedule=newlink';
			} );

		$this->makeController( $token_service, $booking_model, $service_model )
		     ->process_reschedule( $this->validPostParams() );
	}

	/** @test */
	public function process_reschedule_calculates_end_time_from_service_duration(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'add_query_arg' )->alias( function ( string $k, string $v, string $b ): string { return $b . '?' . $k . '=' . $v; } );
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'home_url' )->justReturn( 'http://example.com/' );

		$token_service = \Mockery::mock( WPAPPT_Service_Token::class );
		$token_service->shouldReceive( 'validate' )->andReturn( $this->fakeBooking() );
		$token_service->shouldReceive( 'consume' )->andReturn( str_repeat( 'f', 64 ) );
		$token_service->shouldReceive( 'build_link' )->andReturn( 'http://example.com/?reschedule=fff' );

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		// 90-minute service
		$service_model->shouldReceive( 'find' )->andReturn( [ 'id' => 2, 'name' => 'Deep Tissue', 'duration_mins' => 90 ] );

		$captured = null;
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'count_overlapping' )->andReturn( 0 );
		$booking_model->shouldReceive( 'update' )
		              ->withArgs( function ( int $id, array $data ) use ( &$captured ): bool {
			              $captured = $data;
			              return true;
		              } )
		              ->andReturn( true );
		$booking_model->shouldReceive( 'update_status' )->andReturn( true );

		$this->makeController( $token_service, $booking_model, $service_model )
		     ->process_reschedule( $this->validPostParams( [ 'start_time' => '09:00' ] ) );

		// 09:00 + 90 min = 10:30
		$this->assertSame( '10:30:00', $captured['end_time'] );
	}
}
