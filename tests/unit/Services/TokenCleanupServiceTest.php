<?php

namespace WpAppointments\Tests\Unit\Services;

use Brain\Monkey\Functions;
use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Service_Token_Cleanup;

/**
 * @covers WPAPPT_Service_Token_Cleanup
 */
class TokenCleanupServiceTest extends WpTestCase {

	private function makeService( ?\Mockery\MockInterface $db = null ): WPAPPT_Service_Token_Cleanup {
		return new WPAPPT_Service_Token_Cleanup( $db ?? $this->mockDb() );
	}

	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	// =========================================================================
	// init
	// =========================================================================

	/** @test */
	public function init_registers_expire_tokens_hook(): void {
		Functions\expect( 'add_action' )
			->once()
			->withArgs( function ( string $hook ): bool {
				return $hook === 'wpappt_expire_tokens';
			} );

		$this->makeService()->init();
	}

	// =========================================================================
	// run
	// =========================================================================

	/** @test */
	public function run_executes_update_query_nulling_expired_tokens(): void {
		$db              = $this->mockDb();
		$capturedQuery   = '';

		$db->shouldReceive( 'query' )
		   ->once()
		   ->withArgs( function ( string $sql ) use ( &$capturedQuery ): bool {
			   $capturedQuery = $sql;
			   return true;
		   } );

		$db->rows_affected = 0;

		$this->makeService( $db )->run();

		$this->assertStringContainsString( 'reschedule_token = NULL', $capturedQuery );
		$this->assertStringContainsString( 'token_expires_at = NULL', $capturedQuery );
		$this->assertStringContainsString( 'token_expires_at < NOW()', $capturedQuery );
	}

	/** @test */
	public function run_returns_number_of_affected_rows(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'query' )->once();
		$db->rows_affected = 3;

		$result = $this->makeService( $db )->run();

		$this->assertSame( 3, $result );
	}

	/** @test */
	public function run_returns_zero_when_no_tokens_expired(): void {
		$db = $this->mockDb();
		$db->shouldReceive( 'query' )->once();
		$db->rows_affected = 0;

		$result = $this->makeService( $db )->run();

		$this->assertSame( 0, $result );
	}
}
