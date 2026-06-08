<?php

namespace WpAppointments\Tests\Unit\Services;

use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Service_Availability;
use WPAPPT_Model_Service;
use WPAPPT_Model_Availability;
use WPAPPT_Model_Booking;

/**
 * @covers WPAPPT_Service_Availability
 */
class AvailabilityServiceTest extends WpTestCase {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function makeService(
		?\Mockery\MockInterface $service_model      = null,
		?\Mockery\MockInterface $availability_model = null,
		?\Mockery\MockInterface $booking_model      = null
	): WPAPPT_Service_Availability {
		return new WPAPPT_Service_Availability(
			$service_model      ?? \Mockery::mock( WPAPPT_Model_Service::class ),
			$availability_model ?? \Mockery::mock( WPAPPT_Model_Availability::class ),
			$booking_model      ?? \Mockery::mock( WPAPPT_Model_Booking::class )
		);
	}

	private function fakeService( array $overrides = [] ): array {
		return array_merge( [
			'id'            => 1,
			'name'          => 'Swedish Massage',
			'duration_mins' => 60,
			'is_active'     => 1,
		], $overrides );
	}

	/** A date guaranteed to be in the future for all tests. */
	private function futureDate( int $days_ahead = 7 ): string {
		return ( new \DateTime( "+{$days_ahead} days" ) )->format( 'Y-m-d' );
	}

	private function dayOfWeek( string $date ): int {
		return (int) ( new \DateTime( $date ) )->format( 'w' );
	}

	// =========================================================================
	// Input validation
	// =========================================================================

	/** @test */
	public function it_returns_empty_for_a_past_date(): void {
		$svc = $this->makeService();

		$result = $svc->get_available_slots( '2020-01-01', 1 );

		$this->assertSame( [], $result );
	}

	/** @test */
	public function it_returns_empty_for_an_invalid_date_string(): void {
		$svc = $this->makeService();

		$this->assertSame( [], $svc->get_available_slots( 'not-a-date', 1 ) );
		$this->assertSame( [], $svc->get_available_slots( '2026-13-01',  1 ) );
		$this->assertSame( [], $svc->get_available_slots( '2026-02-30',  1 ) );
	}

	/** @test */
	public function it_returns_empty_when_service_not_found(): void {
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( null );

		$result = $this->makeService( $service_model )->get_available_slots( $this->futureDate(), 99 );

		$this->assertSame( [], $result );
	}

	/** @test */
	public function it_returns_empty_when_service_is_inactive(): void {
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'is_active' => 0 ] ) );

		$result = $this->makeService( $service_model )->get_available_slots( $this->futureDate(), 1 );

		$this->assertSame( [], $result );
	}

	/** @test */
	public function it_returns_empty_when_no_availability_for_that_day(): void {
		$date          = $this->futureDate();
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );

		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );
		$avail_model->shouldReceive( 'find_by_day' )->andReturn( [] );

		$result = $this->makeService( $service_model, $avail_model )->get_available_slots( $date, 1 );

		$this->assertSame( [], $result );
	}

	/** @test */
	public function it_returns_empty_when_day_is_marked_unavailable(): void {
		$date          = $this->futureDate();
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );

		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );
		$avail_model->shouldReceive( 'find_by_day' )->andReturn( [
			[ 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_available' => 0 ],
		] );

		$result = $this->makeService( $service_model, $avail_model )->get_available_slots( $date, 1 );

		$this->assertSame( [], $result );
	}

	// =========================================================================
	// Slot generation
	// =========================================================================

	/** @test */
	public function it_generates_consecutive_non_overlapping_slots(): void {
		$date          = $this->futureDate();
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'duration_mins' => 60 ] ) );
		$avail_model->shouldReceive( 'find_by_day' )->andReturn( [
			[ 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'is_available' => 1 ],
		] );
		$avail_model->shouldReceive( 'find_blocked_for_date' )->andReturn( [] );
		$booking_model->shouldReceive( 'find_by_date' )->andReturn( [] );

		$slots = $this->makeService( $service_model, $avail_model, $booking_model )
		              ->get_available_slots( $date, 1 );

		$this->assertCount( 3, $slots ); // 09:00, 10:00, 11:00

		$this->assertSame( '09:00', $slots[0]['start_time'] );
		$this->assertSame( '10:00', $slots[0]['end_time'] );

		$this->assertSame( '10:00', $slots[1]['start_time'] );
		$this->assertSame( '11:00', $slots[1]['end_time'] );

		$this->assertSame( '11:00', $slots[2]['start_time'] );
		$this->assertSame( '12:00', $slots[2]['end_time'] );
	}

	/** @test */
	public function it_discards_partial_slot_that_would_exceed_window_end(): void {
		$date          = $this->futureDate();
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		// 09:00–10:30 with a 60-min service → only one slot (09:00–10:00).
		// The 10:00–11:00 candidate would exceed 10:30 so it is dropped.
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'duration_mins' => 60 ] ) );
		$avail_model->shouldReceive( 'find_by_day' )->andReturn( [
			[ 'start_time' => '09:00:00', 'end_time' => '10:30:00', 'is_available' => 1 ],
		] );
		$avail_model->shouldReceive( 'find_blocked_for_date' )->andReturn( [] );
		$booking_model->shouldReceive( 'find_by_date' )->andReturn( [] );

		$slots = $this->makeService( $service_model, $avail_model, $booking_model )
		              ->get_available_slots( $date, 1 );

		$this->assertCount( 1, $slots );
		$this->assertSame( '09:00', $slots[0]['start_time'] );
		$this->assertSame( '10:00', $slots[0]['end_time'] );
	}

	// =========================================================================
	// Obstacle filtering
	// =========================================================================

	/** @test */
	public function it_removes_slots_that_overlap_a_blocked_slot(): void {
		$date          = $this->futureDate();
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'duration_mins' => 60 ] ) );
		$avail_model->shouldReceive( 'find_by_day' )->andReturn( [
			[ 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'is_available' => 1 ],
		] );
		// Block 10:00–11:00 → the 10:00 slot must be removed.
		$avail_model->shouldReceive( 'find_blocked_for_date' )->andReturn( [
			[ 'start_time' => '10:00:00', 'end_time' => '11:00:00' ],
		] );
		$booking_model->shouldReceive( 'find_by_date' )->andReturn( [] );

		$slots = $this->makeService( $service_model, $avail_model, $booking_model )
		              ->get_available_slots( $date, 1 );

		$this->assertCount( 2, $slots );
		$start_times = array_column( $slots, 'start_time' );
		$this->assertContains( '09:00', $start_times );
		$this->assertContains( '11:00', $start_times );
		$this->assertNotContains( '10:00', $start_times );
	}

	/** @test */
	public function it_removes_slots_that_overlap_an_existing_booking(): void {
		$date          = $this->futureDate();
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'duration_mins' => 60 ] ) );
		$avail_model->shouldReceive( 'find_by_day' )->andReturn( [
			[ 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'is_available' => 1 ],
		] );
		$avail_model->shouldReceive( 'find_blocked_for_date' )->andReturn( [] );
		// Existing booking at 11:00–12:00 → the 11:00 slot must be removed.
		$booking_model->shouldReceive( 'find_by_date' )->andReturn( [
			[ 'start_time' => '11:00:00', 'end_time' => '12:00:00' ],
		] );

		$slots = $this->makeService( $service_model, $avail_model, $booking_model )
		              ->get_available_slots( $date, 1 );

		$this->assertCount( 2, $slots );
		$start_times = array_column( $slots, 'start_time' );
		$this->assertNotContains( '11:00', $start_times );
	}

	/** @test */
	public function it_removes_slots_that_partially_overlap_an_obstacle(): void {
		$date          = $this->futureDate();
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		// 60-min service, window 09:00–12:00.
		// Blocked 09:30–10:30 → overlaps both 09:00–10:00 and 10:00–11:00.
		// Only 11:00–12:00 should survive.
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'duration_mins' => 60 ] ) );
		$avail_model->shouldReceive( 'find_by_day' )->andReturn( [
			[ 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'is_available' => 1 ],
		] );
		$avail_model->shouldReceive( 'find_blocked_for_date' )->andReturn( [
			[ 'start_time' => '09:30:00', 'end_time' => '10:30:00' ],
		] );
		$booking_model->shouldReceive( 'find_by_date' )->andReturn( [] );

		$slots = $this->makeService( $service_model, $avail_model, $booking_model )
		              ->get_available_slots( $date, 1 );

		$this->assertCount( 1, $slots );
		$this->assertSame( '11:00', $slots[0]['start_time'] );
	}

	/** @test */
	public function it_returns_all_slots_when_day_is_fully_clear(): void {
		$date          = $this->futureDate();
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'duration_mins' => 30 ] ) );
		$avail_model->shouldReceive( 'find_by_day' )->andReturn( [
			[ 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'is_available' => 1 ],
		] );
		$avail_model->shouldReceive( 'find_blocked_for_date' )->andReturn( [] );
		$booking_model->shouldReceive( 'find_by_date' )->andReturn( [] );

		$slots = $this->makeService( $service_model, $avail_model, $booking_model )
		              ->get_available_slots( $date, 1 );

		$this->assertCount( 2, $slots ); // 10:00 and 10:30
	}

	/** @test */
	public function it_combines_slots_from_multiple_availability_windows(): void {
		$date          = $this->futureDate();
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		// Morning and afternoon windows.
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'duration_mins' => 60 ] ) );
		$avail_model->shouldReceive( 'find_by_day' )->andReturn( [
			[ 'start_time' => '09:00:00', 'end_time' => '11:00:00', 'is_available' => 1 ],
			[ 'start_time' => '14:00:00', 'end_time' => '16:00:00', 'is_available' => 1 ],
		] );
		$avail_model->shouldReceive( 'find_blocked_for_date' )->andReturn( [] );
		$booking_model->shouldReceive( 'find_by_date' )->andReturn( [] );

		$slots = $this->makeService( $service_model, $avail_model, $booking_model )
		              ->get_available_slots( $date, 1 );

		$this->assertCount( 4, $slots ); // 09:00, 10:00, 14:00, 15:00
		$start_times = array_column( $slots, 'start_time' );
		$this->assertContains( '09:00', $start_times );
		$this->assertContains( '10:00', $start_times );
		$this->assertContains( '14:00', $start_times );
		$this->assertContains( '15:00', $start_times );
	}

	/** @test */
	public function it_loads_blocked_and_booked_once_per_call_not_per_slot(): void {
		$date          = $this->futureDate();
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'duration_mins' => 60 ] ) );
		$avail_model->shouldReceive( 'find_by_day' )->andReturn( [
			[ 'start_time' => '09:00:00', 'end_time' => '13:00:00', 'is_available' => 1 ],
		] );

		// Both of these must be called exactly once regardless of how many
		// candidate slots are generated.
		$avail_model->shouldReceive( 'find_blocked_for_date' )->once()->andReturn( [] );
		$booking_model->shouldReceive( 'find_by_date' )->once()->andReturn( [] );

		$this->makeService( $service_model, $avail_model, $booking_model )
		     ->get_available_slots( $date, 1 );
	}

	// =========================================================================
	// get_available_dates_in_month
	// =========================================================================

	/** @test */
	public function get_available_dates_in_month_returns_empty_for_inactive_service(): void {
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'is_active' => 0 ] ) );

		$result = $this->makeService( $service_model )
		               ->get_available_dates_in_month( 2026, 6, 1 );

		$this->assertSame( [], $result );
	}

	/** @test */
	public function get_available_dates_in_month_excludes_days_of_week_with_no_template(): void {
		// Weekly template has NO availability entries at all.
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );
		$avail_model->shouldReceive( 'find_all' )->andReturn( [] );
		$avail_model->shouldReceive( 'find_blocked_for_month' )->andReturn( [] );
		$booking_model->shouldReceive( 'find_by_month' )->andReturn( [] );

		$result = $this->makeService( $service_model, $avail_model, $booking_model )
		               ->get_available_dates_in_month( 2026, 6, 1 );

		$this->assertSame( [], $result );
	}

	/** @test */
	public function get_available_dates_in_month_loads_obstacles_once_not_per_day(): void {
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );
		$avail_model->shouldReceive( 'find_all' )->andReturn( [] );

		// Must be called exactly once regardless of how many days are in the month.
		$avail_model->shouldReceive( 'find_blocked_for_month' )->once()->andReturn( [] );
		$booking_model->shouldReceive( 'find_by_month' )->once()->andReturn( [] );

		$this->makeService( $service_model, $avail_model, $booking_model )
		     ->get_available_dates_in_month( 2026, 6, 1 );
	}

	/** @test */
	public function get_available_dates_in_month_returns_dates_that_have_slots(): void {
		// Use a fixed future month far enough ahead that all days pass the "not past" check.
		$year  = 2099;
		$month = 1; // January — 31 days, starts on a Tuesday (day 2).

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'duration_mins' => 60 ] ) );

		// Only Monday (1) has availability.
		$avail_model->shouldReceive( 'find_all' )->andReturn( [
			[ 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'is_available' => 1 ],
		] );
		$avail_model->shouldReceive( 'find_blocked_for_month' )->andReturn( [] );
		$booking_model->shouldReceive( 'find_by_month' )->andReturn( [] );

		$result = $this->makeService( $service_model, $avail_model, $booking_model )
		               ->get_available_dates_in_month( $year, $month, 1 );

		// Every result must be a Monday.
		foreach ( $result as $date ) {
			$dow = (int) ( new \DateTime( $date ) )->format( 'w' );
			$this->assertSame( 1, $dow, "Expected only Mondays, got day-of-week $dow for $date" );
		}

		$this->assertNotEmpty( $result );
	}

	/** @test */
	public function get_available_dates_in_month_excludes_fully_blocked_date(): void {
		$year  = 2099;
		$month = 1;

		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );
		$avail_model   = \Mockery::mock( WPAPPT_Model_Availability::class );
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService( [ 'duration_mins' => 60 ] ) );

		// Monday availability: one 60-min window 09:00–10:00 (exactly one slot).
		$avail_model->shouldReceive( 'find_all' )->andReturn( [
			[ 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'is_available' => 1 ],
		] );

		// 2099-01-07 is the first Monday of January 2099. Block its only slot entirely.
		$avail_model->shouldReceive( 'find_blocked_for_month' )->andReturn( [
			[ 'blocked_date' => '2099-01-07', 'start_time' => '09:00:00', 'end_time' => '10:00:00' ],
		] );
		$booking_model->shouldReceive( 'find_by_month' )->andReturn( [] );

		$result = $this->makeService( $service_model, $avail_model, $booking_model )
		               ->get_available_dates_in_month( $year, $month, 1 );

		$this->assertNotContains( '2099-01-07', $result, '2099-01-07 should be excluded because its only slot is blocked' );
	}
}
