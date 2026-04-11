<?php

namespace WpAppointments\Tests\Unit\Models;

use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Model_Service;

/**
 * @covers WPAPPT_Model_Service
 */
class ServiceModelTest extends WpTestCase {

	private function makeModel( \Mockery\MockInterface $db ): WPAPPT_Model_Service {
		return new WPAPPT_Model_Service( $db );
	}

	// =========================================================================
	// find_all_active
	// =========================================================================

	/** @test */
	public function find_all_active_queries_only_active_services(): void {
		$db = $this->mockDb();

		$db->shouldReceive( 'get_results' )
		   ->once()
		   ->withArgs( function ( string $sql ): bool {
			   return str_contains( $sql, 'is_active = 1' );
		   } )
		   ->andReturn( [] );

		$this->makeModel( $db )->find_all_active();
	}

	/** @test */
	public function find_all_active_returns_empty_array_when_no_rows(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'get_results' )->andReturn( [] );

		$result = $this->makeModel( $db )->find_all_active();

		$this->assertSame( [], $result );
	}

	// =========================================================================
	// find
	// =========================================================================

	/** @test */
	public function find_returns_null_when_row_not_found(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_row' )->andReturn( null );

		$this->assertNull( $this->makeModel( $db )->find( 99 ) );
	}

	/** @test */
	public function find_returns_array_when_row_exists(): void {
		$db  = $this->mockDb();
		$row = [ 'id' => 1, 'name' => 'Swedish Massage', 'is_active' => 1 ];

		$db->shouldReceive( 'prepare' )->andReturn( 'sql' );
		$db->shouldReceive( 'get_row' )->andReturn( $row );

		$this->assertSame( $row, $this->makeModel( $db )->find( 1 ) );
	}

	// =========================================================================
	// create
	// =========================================================================

	/** @test */
	public function create_returns_new_id_on_success(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'insert' )->andReturn( 1 );
		$db->insert_id = 7;

		$result = $this->makeModel( $db )->create( [
			'name'          => 'Hot Stone',
			'duration_mins' => 60,
			'price'         => 80.00,
		] );

		$this->assertSame( 7, $result );
	}

	/** @test */
	public function create_returns_false_on_db_failure(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'insert' )->andReturn( false );

		$result = $this->makeModel( $db )->create( [
			'name'          => 'Hot Stone',
			'duration_mins' => 60,
			'price'         => 80.00,
		] );

		$this->assertFalse( $result );
	}

	// =========================================================================
	// delete (soft delete)
	// =========================================================================

	/** @test */
	public function delete_sets_is_active_to_zero_rather_than_hard_deleting(): void {
		$db = $this->mockDb();

		$db->shouldReceive( 'update' )
		   ->once()
		   ->withArgs( function ( string $table, array $fields ): bool {
			   return isset( $fields['is_active'] ) && $fields['is_active'] === 0;
		   } )
		   ->andReturn( 1 );

		$this->makeModel( $db )->delete( 5 );
	}
}
