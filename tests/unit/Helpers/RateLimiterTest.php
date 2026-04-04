<?php

namespace WpAppointments\Tests\Unit\Helpers;

use Brain\Monkey\Functions;
use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Helper_Rate_Limiter;

/**
 * @covers WPAPPT_Helper_Rate_Limiter
 */
class RateLimiterTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_key' )->returnArg();
	}

	/** @test */
	public function it_allows_the_first_request_and_sets_a_transient(): void {
		Functions\expect( 'get_transient' )->once()->andReturn( false );
		Functions\expect( 'set_transient' )
			->once()
			->withArgs( function ( string $key, array $data, int $ttl ): bool {
				return $data['count'] === 1 && $ttl === 3600;
			} );

		$result = WPAPPT_Helper_Rate_Limiter::check( 'booking', '1.2.3.4', 5, 3600 );

		$this->assertTrue( $result );
	}

	/** @test */
	public function it_allows_subsequent_requests_below_the_limit(): void {
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( [ 'count' => 3, 'since' => time() - 60 ] );

		Functions\expect( 'set_transient' )
			->once()
			->withArgs( function ( string $key, array $data ): bool {
				return $data['count'] === 4;
			} );

		$result = WPAPPT_Helper_Rate_Limiter::check( 'booking', '1.2.3.4', 5, 3600 );

		$this->assertTrue( $result );
	}

	/** @test */
	public function it_blocks_requests_when_limit_is_reached(): void {
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( [ 'count' => 5, 'since' => time() - 60 ] );

		// No set_transient should be called when blocked.
		Functions\expect( 'set_transient' )->never();

		$result = WPAPPT_Helper_Rate_Limiter::check( 'booking', '1.2.3.4', 5, 3600 );

		$this->assertFalse( $result );
	}

	/** @test */
	public function it_preserves_the_original_window_start_on_increment(): void {
		$original_since = time() - 1800; // 30 minutes into the window

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( [ 'count' => 2, 'since' => $original_since ] );

		Functions\expect( 'set_transient' )
			->once()
			->withArgs( function ( string $key, array $data, int $ttl ) use ( $original_since ): bool {
				// The window_start must not be reset to now.
				return $data['since'] === $original_since
					// Remaining TTL should be approximately 30 minutes (1800s).
					&& $ttl > 1700 && $ttl <= 1800;
			} );

		WPAPPT_Helper_Rate_Limiter::check( 'booking', '1.2.3.4', 5, 3600 );
	}

	/** @test */
	public function different_actions_use_different_transient_keys(): void {
		$keys = [];

		Functions\when( 'get_transient' )->returnArg(); // returns the key itself (truthy, count >= max)

		// We only want to capture the key names — set_transient won't be
		// called because get_transient returns a non-false truthy value which
		// the rate limiter treats as count=key string >= 5... actually this
		// won't work cleanly. Let me use a different approach.

		// Reset and use a fresh approach.
		\Mockery::close();
		parent::setUp();
		Functions\when( 'sanitize_key' )->returnArg();

		$captured = [];
		Functions\when( 'get_transient' )->alias( function ( string $key ) use ( &$captured ): bool {
			$captured[] = $key;
			return false;
		} );
		Functions\when( 'set_transient' )->justReturn( true );

		WPAPPT_Helper_Rate_Limiter::check( 'booking',    '1.2.3.4', 5, 3600 );
		WPAPPT_Helper_Rate_Limiter::check( 'reschedule', '1.2.3.4', 5, 3600 );

		$this->assertCount( 2, $captured );
		$this->assertNotSame( $captured[0], $captured[1] );
	}
}
