<?php

namespace WpAppointments\Tests\Unit\Models;

use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Model_Booking;

/**
 * @covers WPAPPT_Model_Booking
 */
class BookingModelTest extends WpTestCase {

	private function makeModel( \Mockery\MockInterface $db ): WPAPPT_Model_Booking {
		return new WPAPPT_Model_Booking( $db );
	}

	/** Returns a valid booking array for reuse across tests. */
	private function fakeBooking( array $overrides = [] ): array {
		return array_merge( [
			'id'               => 1,
			'service_id'       => 2,
			'status'           => 'pending',
			'appointment_date' => '2026-06-01',
			'start_time'       => '10:00:00',
			'end_time'         => '11:00:00',
			'customer_name'    => 'Jane Doe',
			'customer_email'   => 'jane@example.com',
			'customer_phone'   => '0612345678',
			'injury_notes'     => null,
			'comments'         => null,
			'reschedule_token' => 'abc123hash',
			'token_expires_at' => '2026-06-04 10:00:00',
			'admin_notes'      => null,
			'created_at'       => '2026-05-28 09:00:00',
			'updated_at'       => null,
		], $overrides );
	}

	// =========================================================================
	// find
	// =========================================================================

	/** @test */
	public function find_returns_null_when_booking_not_found(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_row' )->andReturn( null );

		$this->assertNull( $this->makeModel( $db )->find( 99 ) );
	}

	/** @test */
	public function find_returns_booking_array_when_found(): void {
		$db      = $this->mockDb();
		$booking = $this->fakeBooking();

		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_row' )->andReturn( $booking );

		$this->assertSame( $booking, $this->makeModel( $db )->find( 1 ) );
	}

	// =========================================================================
	// find_by_token_hash — security-critical
	// =========================================================================

	/** @test */
	public function find_by_token_hash_sql_requires_token_is_not_null(): void {
		$db          = $this->mockDb();
		$capturedSql = '';

		$db->shouldReceive( 'prepare' )
		   ->withArgs( function ( string $sql ) use ( &$capturedSql ): bool {
			   $capturedSql = $sql;
			   return true;
		   } )
		   ->andReturn( 'sql' );

		$db->shouldReceive( 'get_row' )->andReturn( null );

		$this->makeModel( $db )->find_by_token_hash( 'somehash' );

		$this->assertStringContainsString(
			'IS NOT NULL',
			$capturedSql,
			'Query must check reschedule_token IS NOT NULL to prevent matching nulled tokens'
		);
	}

	/** @test */
	public function find_by_token_hash_sql_requires_token_not_expired(): void {
		$db          = $this->mockDb();
		$capturedSql = '';

		$db->shouldReceive( 'prepare' )
		   ->withArgs( function ( string $sql ) use ( &$capturedSql ): bool {
			   $capturedSql = $sql;
			   return true;
		   } )
		   ->andReturn( 'sql' );

		$db->shouldReceive( 'get_row' )->andReturn( null );

		$this->makeModel( $db )->find_by_token_hash( 'somehash' );

		$this->assertStringContainsString(
			'token_expires_at',
			$capturedSql,
			'Query must check token expiry'
		);
	}

	// =========================================================================
	// update_status — security-critical
	// =========================================================================

	/** @test */
	public function update_status_returns_false_for_invalid_status(): void {
		$db = $this->mockDb();

		// Neither find() nor update() should be called for an invalid status.
		$db->shouldReceive( 'prepare' )->never();
		$db->shouldReceive( 'update' )->never();

		$result = $this->makeModel( $db )->update_status( 1, 'approved', 'admin' );

		$this->assertFalse( $result );
	}

	/** @test */
	public function update_status_returns_false_when_booking_not_found(): void {
		$db = $this->mockDb();

		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_row' )->andReturn( null ); // booking not found

		$result = $this->makeModel( $db )->update_status( 99, 'confirmed', 'admin' );

		$this->assertFalse( $result );
	}

	/** @test */
	public function update_status_nulls_reschedule_token_when_cancelling(): void {
		$db             = $this->mockDb();
		$capturedFields = [];

		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_row' )->andReturn( $this->fakeBooking() );
		$db->shouldReceive( 'update' )
		   ->withArgs( function ( string $table, array $fields ) use ( &$capturedFields ): bool {
			   $capturedFields = $fields;
			   return true;
		   } )
		   ->andReturn( 1 );
		$db->shouldReceive( 'insert' )->andReturn( 1 ); // audit log

		$this->makeModel( $db )->update_status( 1, 'cancelled', 'admin' );

		$this->assertArrayHasKey( 'reschedule_token', $capturedFields,
			'Cancelled booking must have reschedule_token in the UPDATE'
		);
		$this->assertNull( $capturedFields['reschedule_token'],
			'Token must be set to NULL so outstanding links cannot be used after cancellation'
		);
		$this->assertNull( $capturedFields['token_expires_at'],
			'Token expiry must also be nulled on cancellation'
		);
	}

	/** @test */
	public function update_status_does_not_null_token_when_confirming(): void {
		$db             = $this->mockDb();
		$capturedFields = [];

		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_row' )->andReturn( $this->fakeBooking() );
		$db->shouldReceive( 'update' )
		   ->withArgs( function ( string $table, array $fields ) use ( &$capturedFields ): bool {
			   $capturedFields = $fields;
			   return true;
		   } )
		   ->andReturn( 1 );
		$db->shouldReceive( 'insert' )->andReturn( 1 ); // audit log

		$this->makeModel( $db )->update_status( 1, 'confirmed', 'admin' );

		$this->assertArrayNotHasKey( 'reschedule_token', $capturedFields,
			'Confirming should not touch the reschedule token'
		);
	}

	/** @test */
	public function update_status_writes_to_audit_log(): void {
		$db = $this->mockDb();

		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_row' )->andReturn( $this->fakeBooking( [ 'status' => 'pending' ] ) );
		$db->shouldReceive( 'update' )->andReturn( 1 );

		$auditInsertCalled = false;
		$db->shouldReceive( 'insert' )
		   ->withArgs( function ( string $table ) use ( &$auditInsertCalled ): bool {
			   if ( str_contains( $table, 'audit_log' ) ) {
				   $auditInsertCalled = true;
			   }
			   return true;
		   } )
		   ->andReturn( 1 );

		$this->makeModel( $db )->update_status( 1, 'confirmed', 'admin' );

		$this->assertTrue( $auditInsertCalled, 'Audit log must be written on every status transition' );
	}

	// =========================================================================
	// find_all — SQL injection / whitelist
	// =========================================================================

	/** @test */
	public function find_all_escapes_search_term_with_esc_like(): void {
		$db = $this->mockDb();

		$db->shouldReceive( 'esc_like' )
		   ->once()
		   ->with( 'test%user' )
		   ->andReturn( 'test\%user' );

		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_results' )->andReturn( [] );

		$this->makeModel( $db )->find_all( [ 'search' => 'test%user' ] );
	}

	/** @test */
	public function find_all_rejects_non_whitelisted_orderby_column(): void {
		$db             = $this->mockDb();
		$capturedQuery  = '';

		// build_limit() always produces placeholder values so prepare() is
		// always called regardless of whether filters are present.
		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_results' )
		   ->withArgs( function ( string $sql ) use ( &$capturedQuery ): bool {
			   $capturedQuery = $sql;
			   return true;
		   } )
		   ->andReturn( [] );

		$this->makeModel( $db )->find_all( [ 'orderby' => 'DROP TABLE wp_users; --' ] );

		$this->assertStringNotContainsString(
			'DROP TABLE',
			$capturedQuery,
			'Non-whitelisted ORDER BY column must be silently replaced with the default'
		);
	}

	// =========================================================================
	// find_by_date
	// =========================================================================

	/** @test */
	public function find_by_date_queries_by_date_and_excludes_cancelled(): void {
		$db          = $this->mockDb();
		$capturedSql = '';

		$db->shouldReceive( 'prepare' )
		   ->withArgs( function ( string $sql ) use ( &$capturedSql ): bool {
			   $capturedSql = $sql;
			   return true;
		   } )
		   ->andReturn( 'sql' );

		$db->shouldReceive( 'get_results' )->andReturn( [] );

		$this->makeModel( $db )->find_by_date( '2026-06-15' );

		$this->assertStringContainsString( "status != 'cancelled'", $capturedSql );
	}

	/** @test */
	public function find_by_date_returns_empty_array_when_no_bookings(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_results' )->andReturn( [] );

		$this->assertSame( [], $this->makeModel( $db )->find_by_date( '2026-06-15' ) );
	}

	// =========================================================================
	// count_overlapping
	// =========================================================================

	/** @test */
	public function count_overlapping_sql_uses_time_range_overlap_logic(): void {
		$db          = $this->mockDb();
		$capturedSql = '';

		$db->shouldReceive( 'prepare' )
		   ->withArgs( function ( string $sql ) use ( &$capturedSql ): bool {
			   $capturedSql = $sql;
			   return true;
		   } )
		   ->andReturn( 'sql' );

		$db->shouldReceive( 'get_var' )->andReturn( '0' );

		$this->makeModel( $db )->count_overlapping( '2026-06-01', '10:00', '11:00' );

		// Overlap: existing.start_time < new.end_time AND existing.end_time > new.start_time
		$this->assertStringContainsString( 'start_time < %s', $capturedSql );
		$this->assertStringContainsString( 'end_time   > %s', $capturedSql );
	}

	/** @test */
	public function count_overlapping_excludes_cancelled_bookings(): void {
		$db          = $this->mockDb();
		$capturedSql = '';

		$db->shouldReceive( 'prepare' )
		   ->withArgs( function ( string $sql ) use ( &$capturedSql ): bool {
			   $capturedSql = $sql;
			   return true;
		   } )
		   ->andReturn( 'sql' );

		$db->shouldReceive( 'get_var' )->andReturn( '0' );

		$this->makeModel( $db )->count_overlapping( '2026-06-01', '10:00', '11:00' );

		$this->assertStringContainsString( "status != 'cancelled'", $capturedSql );
	}
}
