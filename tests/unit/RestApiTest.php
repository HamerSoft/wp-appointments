<?php

namespace WpAppointments\Tests\Unit;

use Brain\Monkey\Functions;
use WPAPPT_Model_Service;
use WPAPPT_Rest_Api;
use WPAPPT_Service_Availability;

/**
 * @covers WPAPPT_Rest_Api::get_services
 * @covers WPAPPT_Rest_Api::get_availability
 */
class RestApiTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Model constructors read $wpdb->prefix. Provide a stub so that the
		// default fallback instances created by WPAPPT_Rest_Api's constructor
		// do not throw when a test only passes one of the two dependencies.
		$wpdb            = \Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}


	// =========================================================================
	// GET /services
	// =========================================================================

	/** @test */
	public function get_services_returns_200_with_shaped_service_data(): void {
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find_all_active' )->once()->andReturn( [
			[ 'id' => '1', 'name' => 'Swedish Massage', 'duration_mins' => '60', 'price' => '55.00', 'is_active' => '1' ],
			[ 'id' => '2', 'name' => 'Deep Tissue',     'duration_mins' => '90', 'price' => '75.50', 'is_active' => '1' ],
		] );

		$api      = new WPAPPT_Rest_Api( $service_model );
		$response = $api->get_services();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 2, $data );

		// Values are cast to the correct types.
		$this->assertSame( 1,     $data[0]['id'] );
		$this->assertSame( 60,    $data[0]['duration_mins'] );
		$this->assertSame( 55.0,  $data[0]['price'] );
		$this->assertSame( 'Swedish Massage', $data[0]['name'] );
	}

	/** @test */
	public function get_services_returns_empty_array_when_no_active_services(): void {
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find_all_active' )->once()->andReturn( [] );

		$response = ( new WPAPPT_Rest_Api( $service_model ) )->get_services();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $response->get_data() );
	}

	/** @test */
	public function get_services_does_not_expose_is_active_or_other_internal_fields(): void {
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find_all_active' )->andReturn( [
			[ 'id' => '1', 'name' => 'Swedish', 'duration_mins' => '60', 'price' => '55.00', 'is_active' => '1', 'sort_order' => '0' ],
		] );

		$data = ( new WPAPPT_Rest_Api( $service_model ) )->get_services()->get_data();

		$this->assertArrayNotHasKey( 'is_active',   $data[0] );
		$this->assertArrayNotHasKey( 'sort_order',  $data[0] );
		$this->assertArrayNotHasKey( 'created_at',  $data[0] );
	}

	// =========================================================================
	// GET /availability
	// =========================================================================

	/** @test */
	public function get_availability_returns_200_with_slots_from_availability_service(): void {
		$slots = [
			[ 'start_time' => '09:00', 'end_time' => '10:00' ],
			[ 'start_time' => '10:00', 'end_time' => '11:00' ],
		];

		$availability_service = \Mockery::mock( WPAPPT_Service_Availability::class );
		$availability_service->shouldReceive( 'get_available_slots' )
			->once()
			->with( '2026-06-01', 1 )
			->andReturn( $slots );

		$request = new \WP_REST_Request( [ 'service_id' => 1, 'date' => '2026-06-01' ] );

		$response = ( new WPAPPT_Rest_Api( null, $availability_service ) )->get_availability( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $slots, $response->get_data() );
	}

	/** @test */
	public function get_availability_returns_empty_array_when_no_slots_available(): void {
		$availability_service = \Mockery::mock( WPAPPT_Service_Availability::class );
		$availability_service->shouldReceive( 'get_available_slots' )->andReturn( [] );

		$request  = new \WP_REST_Request( [ 'service_id' => 1, 'date' => '2026-06-01' ] );
		$response = ( new WPAPPT_Rest_Api( null, $availability_service ) )->get_availability( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $response->get_data() );
	}
}
