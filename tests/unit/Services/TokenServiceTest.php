<?php

namespace WpAppointments\Tests\Unit\Services;

use Brain\Monkey\Functions;
use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Service_Token;
use WPAPPT_Model_Booking;

/**
 * @covers WPAPPT_Service_Token
 */
class TokenServiceTest extends WpTestCase {

	// -------------------------------------------------------------------------
	// Fixtures
	// -------------------------------------------------------------------------

	private function makeService( ?\Mockery\MockInterface $booking_model = null ): WPAPPT_Service_Token {
		return new WPAPPT_Service_Token(
			$booking_model ?? \Mockery::mock( WPAPPT_Model_Booking::class )
		);
	}

	private function fakeBookingWithHash( string $hash ): array {
		return [
			'id'               => 1,
			'service_id'       => 2,
			'status'           => 'confirmed',
			'appointment_date' => '2026-07-01',
			'start_time'       => '10:00:00',
			'end_time'         => '11:00:00',
			'customer_name'    => 'Jane Doe',
			'customer_email'   => 'jane@example.com',
			'reschedule_token' => $hash,
			'token_expires_at' => '2099-01-01 00:00:00',
		];
	}

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'error_log' )->justReturn( null );
	}

	// =========================================================================
	// generate()
	// =========================================================================

	/** @test */
	public function generate_returns_a_64_char_hex_string(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'set_reschedule_token' )->once();

		$token = $this->makeService( $booking_model )->generate( 1 );

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $token );
	}

	/** @test */
	public function generate_stores_sha256_hash_not_raw_token(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		$stored_hash = null;
		$booking_model->shouldReceive( 'set_reschedule_token' )
		              ->once()
		              ->withArgs( function ( int $id, string $hash, string $expires ) use ( &$stored_hash ): bool {
			              $stored_hash = $hash;
			              return true;
		              } );

		$raw_token = $this->makeService( $booking_model )->generate( 1 );

		// The stored value must be the SHA-256 hash of the raw token, not the
		// raw token itself.
		$this->assertSame( hash( 'sha256', $raw_token ), $stored_hash );
		$this->assertNotSame( $raw_token, $stored_hash );
	}

	/** @test */
	public function generate_stores_expiry_72_hours_in_future(): void {
		$before = time() + ( 72 * 3600 );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );

		$stored_expires = null;
		$booking_model->shouldReceive( 'set_reschedule_token' )
		              ->once()
		              ->withArgs( function ( int $id, string $hash, string $expires ) use ( &$stored_expires ): bool {
			              $stored_expires = $expires;
			              return true;
		              } );

		$this->makeService( $booking_model )->generate( 1 );

		$after      = time() + ( 72 * 3600 );
		$stored_ts  = strtotime( $stored_expires );

		$this->assertGreaterThanOrEqual( $before, $stored_ts );
		$this->assertLessThanOrEqual( $after + 1, $stored_ts );
	}

	// =========================================================================
	// validate()
	// =========================================================================

	/** @test */
	public function validate_returns_null_for_empty_token(): void {
		$result = $this->makeService()->validate( '' );

		$this->assertNull( $result );
	}

	/** @test */
	public function validate_returns_null_when_booking_not_found(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'find_by_token_hash' )->andReturn( null );

		$result = $this->makeService( $booking_model )->validate( 'some-raw-token' );

		$this->assertNull( $result );
	}

	/** @test */
	public function validate_returns_booking_for_correct_raw_token(): void {
		$raw_token = str_repeat( 'a', 64 ); // fake 64-char hex token
		$hash      = hash( 'sha256', $raw_token );
		$booking   = $this->fakeBookingWithHash( $hash );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'find_by_token_hash' )
		              ->with( $hash )
		              ->andReturn( $booking );

		$result = $this->makeService( $booking_model )->validate( $raw_token );

		$this->assertSame( $booking, $result );
	}

	/** @test */
	public function validate_uses_hash_equals_and_rejects_mismatched_hash(): void {
		// DB returns a booking whose stored hash does NOT match the supplied token.
		$raw_token      = str_repeat( 'b', 64 );
		$different_hash = hash( 'sha256', 'completely-different-raw-token' );

		$booking_with_wrong_hash              = $this->fakeBookingWithHash( $different_hash );
		$computed_hash                        = hash( 'sha256', $raw_token );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'find_by_token_hash' )
		              ->with( $computed_hash )
		              ->andReturn( $booking_with_wrong_hash );

		$result = $this->makeService( $booking_model )->validate( $raw_token );

		$this->assertNull( $result );
	}

	// =========================================================================
	// consume()
	// =========================================================================

	/** @test */
	public function consume_generates_a_fresh_token_overwriting_the_old_one(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		// set_reschedule_token is called once (via generate, via consume).
		$booking_model->shouldReceive( 'set_reschedule_token' )->once()->andReturn( true );

		$new_token = $this->makeService( $booking_model )->consume( 1 );

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $new_token );
	}

	// =========================================================================
	// build_link()
	// =========================================================================

	/** @test */
	public function build_link_appends_token_as_reschedule_query_param(): void {
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'home_url' )->justReturn( 'http://example.com/' );
		Functions\when( 'add_query_arg' )->alias( function ( string $key, string $val, string $base ): string {
			return $base . '?' . $key . '=' . $val;
		} );

		$link = $this->makeService()->build_link( 'myrawtoken' );

		$this->assertStringContainsString( 'reschedule=myrawtoken', $link );
	}

	/** @test */
	public function build_link_uses_booking_page_when_configured(): void {
		Functions\when( 'get_option' )->justReturn( 5 );
		Functions\when( 'get_permalink' )->justReturn( 'http://example.com/book/' );
		Functions\when( 'add_query_arg' )->alias( function ( string $key, string $val, string $base ): string {
			return $base . '?' . $key . '=' . $val;
		} );

		$link = $this->makeService()->build_link( 'tok' );

		$this->assertStringContainsString( 'example.com/book/', $link );
	}

	// =========================================================================
	// init() / on_status_changed()
	// =========================================================================

	/** @test */
	public function init_registers_status_changed_hook_at_priority_5(): void {
		Functions\expect( 'add_action' )
			->once()
			->withArgs( function ( string $hook, callable $cb, int $priority, int $accepted_args ): bool {
				return $hook === 'wpappt_booking_status_changed'
				    && $priority === 5
				    && $accepted_args === 4;
			} );

		$this->makeService()->init();
	}

	/** @test */
	public function on_status_changed_generates_token_and_fires_confirmed_action_for_confirmed(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'set_reschedule_token' )->once()->andReturn( true );

		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'home_url' )->justReturn( 'http://example.com/' );
		Functions\when( 'add_query_arg' )->alias( function ( string $key, string $val, string $base ): string {
			return $base . '?' . $key . '=' . $val;
		} );

		Functions\expect( 'do_action' )
			->once()
			->withArgs( function ( string $hook, int $id, string $link, array $attachments ): bool {
				return $hook === 'wpappt_booking_confirmed'
				    && $id === 1
				    && str_contains( $link, 'reschedule=' )
				    && $attachments === [];
			} );

		$this->makeService( $booking_model )->on_status_changed( 1, 'confirmed' );
	}

	/** @test */
	public function on_status_changed_does_nothing_for_non_confirmed_statuses(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldNotReceive( 'set_reschedule_token' );

		Functions\expect( 'do_action' )->never();

		$this->makeService( $booking_model )->on_status_changed( 1, 'cancelled' );
		$this->makeService( $booking_model )->on_status_changed( 1, 'pending' );
	}

	// =========================================================================
	// Attachment passthrough
	// =========================================================================

	/** @test */
	public function on_status_changed_passes_attachments_to_booking_confirmed_action(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'set_reschedule_token' )->once();

		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'home_url' )->justReturn( 'http://example.com/' );
		Functions\when( 'add_query_arg' )->justReturn( 'http://example.com/?reschedule=abc' );

		$capturedAttachments = 'not-set';
		Functions\when( 'do_action' )->alias(
			function ( string $hook, ...$args ) use ( &$capturedAttachments ): void {
				if ( 'wpappt_booking_confirmed' === $hook ) {
					$capturedAttachments = $args[2] ?? [];
				}
			}
		);

		$this->makeService( $booking_model )
		     ->on_status_changed( 1, 'confirmed', 'admin', [ '/tmp/intake.pdf' ] );

		$this->assertSame( [ '/tmp/intake.pdf' ], $capturedAttachments );
	}

	/** @test */
	public function on_status_changed_passes_empty_attachments_when_omitted(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'set_reschedule_token' )->once();

		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'home_url' )->justReturn( 'http://example.com/' );
		Functions\when( 'add_query_arg' )->justReturn( 'http://example.com/?reschedule=abc' );

		$capturedAttachments = 'not-set';
		Functions\when( 'do_action' )->alias(
			function ( string $hook, ...$args ) use ( &$capturedAttachments ): void {
				if ( 'wpappt_booking_confirmed' === $hook ) {
					$capturedAttachments = $args[2] ?? [];
				}
			}
		);

		$this->makeService( $booking_model )
		     ->on_status_changed( 1, 'confirmed' );

		$this->assertSame( [], $capturedAttachments );
	}
}
