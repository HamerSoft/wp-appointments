<?php

namespace WpAppointments\Tests\Unit\Models;

use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Model_Availability;

/**
 * @covers WPAPPT_Model_Availability
 */
class AvailabilityModelTest extends WpTestCase {

	private function makeModel( \Mockery\MockInterface $db ): WPAPPT_Model_Availability {
		return new WPAPPT_Model_Availability( $db );
	}

	// =========================================================================
	// find_blocked_for_date
	// =========================================================================

	/** @test */
	public function find_blocked_for_date_queries_by_exact_date(): void {
		$db = $this->mockDb();

		$db->shouldReceive( 'prepare' )
		   ->once()
		   ->withArgs( function ( string $sql, string $date ): bool {
			   return str_contains( $sql, 'blocked_date = %s' ) && $date === '2026-06-15';
		   } )
		   ->andReturn( 'sql' );

		$db->shouldReceive( 'get_results' )->andReturn( [] );

		$this->makeModel( $db )->find_blocked_for_date( '2026-06-15' );
	}

	/** @test */
	public function find_blocked_for_date_returns_empty_array_when_no_rows(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_results' )->andReturn( [] );

		$this->assertSame( [], $this->makeModel( $db )->find_blocked_for_date( '2026-06-15' ) );
	}

	// =========================================================================
	// save_day
	// =========================================================================

	/** @test */
	public function save_day_deletes_existing_rows_before_inserting(): void {
		$db = $this->mockDb();

		$db->shouldReceive( 'delete' )
		   ->once()
		   ->withArgs( function ( string $table, array $where ): bool {
			   return $where['day_of_week'] === 1; // Monday
		   } )
		   ->andReturn( 1 );

		$db->shouldReceive( 'insert' )->andReturn( 1 );

		$this->makeModel( $db )->save_day( 1, [
			[ 'start_time' => '09:00', 'end_time' => '17:00', 'is_available' => 1 ],
		] );
	}

	/** @test */
	public function save_day_with_empty_slots_deletes_rows_and_inserts_nothing(): void {
		$db = $this->mockDb();

		$db->shouldReceive( 'delete' )->once()->andReturn( 1 );
		$db->shouldReceive( 'insert' )->never();

		$this->makeModel( $db )->save_day( 1, [] );
	}

	// =========================================================================
	// add_blocked_slot
	// =========================================================================

	/** @test */
	public function add_blocked_slot_returns_new_id_on_success(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'insert' )->andReturn( 1 );
		$db->insert_id = 3;

		$result = $this->makeModel( $db )->add_blocked_slot( [
			'blocked_date' => '2026-07-04',
			'start_time'   => '12:00',
			'end_time'     => '14:00',
			'reason'       => null,
		] );

		$this->assertSame( 3, $result );
	}

	/** @test */
	public function add_blocked_slot_returns_false_on_db_failure(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'insert' )->andReturn( false );

		$this->assertFalse(
			$this->makeModel( $db )->add_blocked_slot( [
				'blocked_date' => '2026-07-04',
				'start_time'   => '12:00',
				'end_time'     => '14:00',
				'reason'       => null,
			] )
		);
	}

	// =========================================================================
	// delete_blocked_slot
	// =========================================================================

	/** @test */
	public function delete_blocked_slot_returns_true_when_row_deleted(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'delete' )->andReturn( 1 );

		$this->assertTrue( $this->makeModel( $db )->delete_blocked_slot( 4 ) );
	}

	/** @test */
	public function delete_blocked_slot_returns_false_when_row_not_found(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'delete' )->andReturn( 0 );

		$this->assertFalse( $this->makeModel( $db )->delete_blocked_slot( 999 ) );
	}

	// =========================================================================
	// add_blocked_slots_batch
	// =========================================================================

	/** @test */
	public function add_blocked_slots_batch_returns_false_for_empty_input(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'insert' )->never();

		$this->assertFalse( $this->makeModel( $db )->add_blocked_slots_batch( [] ) );
	}

	/** @test */
	public function add_blocked_slots_batch_returns_false_when_insert_fails(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'insert' )->once()->andReturn( false );

		$this->assertFalse(
			$this->makeModel( $db )->add_blocked_slots_batch( [
				[ 'blocked_date' => '2026-07-01', 'start_time' => '09:00', 'end_time' => '10:00', 'reason' => null ],
			] )
		);
	}

	/** @test */
	public function add_blocked_slots_batch_inserts_all_rows_and_returns_first_id(): void {
		$db            = $this->mockDb();
		$db->insert_id = 7;

		$db->shouldReceive( 'insert' )->twice()->andReturn( 1 );

		$db->shouldReceive( 'prepare' )
		   ->once()
		   ->andReturn( 'back-fill-sql' );

		$db->shouldReceive( 'query' )->once()->with( 'back-fill-sql' );

		$result = $this->makeModel( $db )->add_blocked_slots_batch( [
			[ 'blocked_date' => '2026-07-01', 'start_time' => '09:00', 'end_time' => '10:00', 'reason' => null ],
			[ 'blocked_date' => '2026-07-08', 'start_time' => '09:00', 'end_time' => '10:00', 'reason' => null ],
		] );

		$this->assertSame( 7, $result );
	}

	// =========================================================================
	// delete_blocked_series
	// =========================================================================

	/** @test */
	public function delete_blocked_series_returns_true_when_rows_deleted(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'delete' )
		   ->once()
		   ->withArgs( function ( string $table, array $where ): bool {
			   return isset( $where['series_id'] ) && $where['series_id'] === 5;
		   } )
		   ->andReturn( 3 );

		$this->assertTrue( $this->makeModel( $db )->delete_blocked_series( 5 ) );
	}

	/** @test */
	public function delete_blocked_series_returns_false_when_no_rows_found(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'delete' )->once()->andReturn( 0 );

		$this->assertFalse( $this->makeModel( $db )->delete_blocked_series( 999 ) );
	}

	// =========================================================================
	// find_blocked_for_month
	// =========================================================================

	/** @test */
	public function find_blocked_for_month_queries_by_year_month_prefix(): void {
		$db = $this->mockDb();

		$db->shouldReceive( 'prepare' )
		   ->once()
		   ->withArgs( function ( string $sql, string $prefix ): bool {
			   return str_contains( $sql, "blocked_date LIKE %s" ) && $prefix === '2026-06%';
		   } )
		   ->andReturn( 'sql' );

		$db->shouldReceive( 'get_results' )->andReturn( [] );

		$this->makeModel( $db )->find_blocked_for_month( '2026-06' );
	}

	/** @test */
	public function find_blocked_for_month_returns_empty_array_when_no_rows(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_results' )->andReturn( [] );

		$this->assertSame( [], $this->makeModel( $db )->find_blocked_for_month( '2026-06' ) );
	}
}
