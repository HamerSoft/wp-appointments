<?php

namespace WpAppointments\Tests\Unit\Controllers;

use Brain\Monkey\Functions;
use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Controller_Booking;
use WPAPPT_Model_Booking;
use WPAPPT_Model_Service;
use WPAPPT_Service_Availability;

/**
 * @covers WPAPPT_Controller_Booking
 *
 * Tests call process_create() directly to stay independent of WP_REST_Request.
 */
class BookingControllerTest extends WpTestCase {

	// -------------------------------------------------------------------------
	// Fixtures
	// -------------------------------------------------------------------------

	private function makeController(
		?\Mockery\MockInterface $service_model        = null,
		?\Mockery\MockInterface $booking_model        = null,
		?\Mockery\MockInterface $availability_service = null
	): WPAPPT_Controller_Booking {
		return new WPAPPT_Controller_Booking(
			$service_model        ?? \Mockery::mock( WPAPPT_Model_Service::class ),
			$booking_model        ?? \Mockery::mock( WPAPPT_Model_Booking::class ),
			$availability_service ?? \Mockery::mock( WPAPPT_Service_Availability::class )
		);
	}

	private function validParams( array $overrides = [] ): array {
		return array_merge( [
			'service_id'       => 1,
			'appointment_date' => ( new \DateTime( '+7 days' ) )->format( 'Y-m-d' ),
			'start_time'       => '10:00',
			'customer_name'    => 'Jane Doe',
			'customer_email'   => 'jane@example.com',
			'customer_phone'   => '0612345678',
			'injury_notes'     => '',
			'comments'         => '',
		], $overrides );
	}

	protected function setUp(): void {
		parent::setUp();

		// Default Brain Monkey stubs for WP functions used in every path.
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'sanitize_textarea_field' )->returnArg();
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( '__' )->returnArg();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * Install a wpdb mock in the global scope and return it.
	 * The controller uses `global $wpdb` for transaction queries.
	 */
	private function mockWpdb(): \Mockery\MockInterface {
		$wpdb            = \Mockery::mock( 'wpdb' );
		$GLOBALS['wpdb'] = $wpdb;
		return $wpdb;
	}

	// =========================================================================
	// Rate limiting
	// =========================================================================

	/** @test */
	public function it_returns_429_when_rate_limit_is_exceeded(): void {
		Functions\expect( 'get_transient' )->andReturn( [ 'count' => 5, 'since' => time() ] );

		$result = $this->makeController()->process_create( $this->validParams() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rate_limited', $result->get_error_code() );
		$this->assertSame( 429, $result->get_error_data()['status'] );
	}

	// =========================================================================
	// Validation
	// =========================================================================

	/** @test */
	public function it_returns_422_when_customer_name_is_empty(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$result = $this->makeController()->process_create(
			$this->validParams( [ 'customer_name' => '' ] )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_field', $result->get_error_code() );
		$this->assertSame( 422, $result->get_error_data()['status'] );
	}

	/** @test */
	public function it_returns_422_when_email_is_invalid(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'is_email' )->justReturn( false );

		$result = $this->makeController()->process_create(
			$this->validParams( [ 'customer_email' => 'not-an-email' ] )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_email', $result->get_error_code() );
		$this->assertSame( 422, $result->get_error_data()['status'] );
	}

	/** @test */
	public function it_returns_422_when_date_is_in_the_past(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$result = $this->makeController()->process_create(
			$this->validParams( [ 'appointment_date' => '2020-01-01' ] )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'past_date', $result->get_error_code() );
		$this->assertSame( 422, $result->get_error_data()['status'] );
	}

	/** @test */
	public function it_returns_422_when_date_format_is_invalid(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$result = $this->makeController()->process_create(
			$this->validParams( [ 'appointment_date' => 'not-a-date' ] )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_date', $result->get_error_code() );
	}

	/** @test */
	public function it_returns_422_when_start_time_format_is_invalid(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$result = $this->makeController()->process_create(
			$this->validParams( [ 'start_time' => 'not-a-time' ] )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_time', $result->get_error_code() );
	}

	// =========================================================================
	// Slot availability
	// =========================================================================

	/** @test */
	public function it_returns_409_when_requested_slot_is_not_available(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$availability = \Mockery::mock( WPAPPT_Service_Availability::class );
		$availability->shouldReceive( 'get_available_slots' )->andReturn( [
			[ 'start_time' => '11:00', 'end_time' => '12:00' ],
		] );

		$result = $this->makeController( null, null, $availability )->process_create(
			$this->validParams( [ 'start_time' => '10:00' ] ) // 10:00 not in available list
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'slot_unavailable', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
	}

	/** @test */
	public function it_returns_409_when_slot_is_taken_between_pre_check_and_insert(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$wpdb = $this->mockWpdb();
		$wpdb->shouldReceive( 'query' )->with( 'START TRANSACTION' )->once();
		$wpdb->shouldReceive( 'query' )->with( 'ROLLBACK' )->once();

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( [
			'id' => 1, 'duration_mins' => 60, 'is_active' => 1,
		] );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'count_overlapping_for_update' )->andReturn( 1 );
		$booking_model->shouldNotReceive( 'create' );

		$availability = \Mockery::mock( WPAPPT_Service_Availability::class );
		$availability->shouldReceive( 'get_available_slots' )->andReturn( [
			[ 'start_time' => '10:00', 'end_time' => '11:00' ],
		] );

		$result = $this->makeController( $service_model, $booking_model, $availability )
		               ->process_create( $this->validParams() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'slot_taken', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
	}

	// =========================================================================
	// Happy path
	// =========================================================================

	/** @test */
	public function it_returns_booking_id_on_success(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );

		$wpdb = $this->mockWpdb();
		$wpdb->shouldReceive( 'query' )->with( 'START TRANSACTION' )->once();
		$wpdb->shouldReceive( 'query' )->with( 'COMMIT' )->once();

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( [
			'id' => 1, 'name' => 'Swedish', 'duration_mins' => 60, 'is_active' => 1,
		] );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'count_overlapping_for_update' )->andReturn( 0 );
		$booking_model->shouldReceive( 'create' )->andReturn( 42 );

		$availability = \Mockery::mock( WPAPPT_Service_Availability::class );
		$availability->shouldReceive( 'get_available_slots' )->andReturn( [
			[ 'start_time' => '10:00', 'end_time' => '11:00' ],
		] );

		$result = $this->makeController( $service_model, $booking_model, $availability )
		               ->process_create( $this->validParams() );

		$this->assertIsArray( $result );
		$this->assertSame( 42, $result['booking_id'] );
	}

	/** @test */
	public function it_fires_booking_created_hook_with_new_booking_id(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$wpdb = $this->mockWpdb();
		$wpdb->shouldReceive( 'query' )->with( 'START TRANSACTION' )->once();
		$wpdb->shouldReceive( 'query' )->with( 'COMMIT' )->once();

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( [
			'id' => 1, 'duration_mins' => 60, 'is_active' => 1,
		] );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'count_overlapping_for_update' )->andReturn( 0 );
		$booking_model->shouldReceive( 'create' )->andReturn( 7 );

		$availability = \Mockery::mock( WPAPPT_Service_Availability::class );
		$availability->shouldReceive( 'get_available_slots' )->andReturn( [
			[ 'start_time' => '10:00', 'end_time' => '11:00' ],
		] );

		Functions\expect( 'do_action' )
			->once()
			->withArgs( function ( string $hook, int $id ): bool {
				return $hook === 'wpappt_booking_created' && $id === 7;
			} );

		$this->makeController( $service_model, $booking_model, $availability )
		     ->process_create( $this->validParams() );
	}

	/** @test */
	public function it_returns_500_when_booking_model_fails_to_insert(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$wpdb = $this->mockWpdb();
		$wpdb->shouldReceive( 'query' )->with( 'START TRANSACTION' )->once();
		$wpdb->shouldReceive( 'query' )->with( 'ROLLBACK' )->once();

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( [
			'id' => 1, 'duration_mins' => 60, 'is_active' => 1,
		] );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'count_overlapping_for_update' )->andReturn( 0 );
		$booking_model->shouldReceive( 'create' )->andReturn( false );

		$availability = \Mockery::mock( WPAPPT_Service_Availability::class );
		$availability->shouldReceive( 'get_available_slots' )->andReturn( [
			[ 'start_time' => '10:00', 'end_time' => '11:00' ],
		] );

		$result = $this->makeController( $service_model, $booking_model, $availability )
		               ->process_create( $this->validParams() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'booking_failed', $result->get_error_code() );
		$this->assertSame( 500, $result->get_error_data()['status'] );
	}

	/** @test */
	public function it_calculates_end_time_from_service_duration_not_from_client(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );

		$wpdb = $this->mockWpdb();
		$wpdb->shouldReceive( 'query' )->with( 'START TRANSACTION' )->once();
		$wpdb->shouldReceive( 'query' )->with( 'COMMIT' )->once();

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( [
			'id' => 1, 'duration_mins' => 90, 'is_active' => 1,
		] );

		$captured_data = null;
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'count_overlapping_for_update' )->andReturn( 0 );
		$booking_model->shouldReceive( 'create' )
		              ->withArgs( function ( array $data ) use ( &$captured_data ): bool {
			              $captured_data = $data;
			              return true;
		              } )
		              ->andReturn( 1 );

		$availability = \Mockery::mock( WPAPPT_Service_Availability::class );
		$availability->shouldReceive( 'get_available_slots' )->andReturn( [
			[ 'start_time' => '10:00', 'end_time' => '11:30' ],
		] );

		$this->makeController( $service_model, $booking_model, $availability )
		     ->process_create( $this->validParams( [ 'start_time' => '10:00' ] ) );

		// 10:00 + 90 min = 11:30
		$this->assertSame( '11:30:00', $captured_data['end_time'] );
	}
}
