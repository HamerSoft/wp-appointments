<?php

namespace WpAppointments\Tests\Unit\Helpers;

use Brain\Monkey\Functions;
use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Helper_Sanitizer;

/**
 * @covers WPAPPT_Helper_Sanitizer
 */
class SanitizerTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Make WP sanitisation functions behave as pass-throughs so tests
		// focus on the sanitizer's own logic (type casts, key selection, etc.).
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'sanitize_textarea_field' )->returnArg();
	}

	// =========================================================================
	// email_header_value
	// =========================================================================

	/** @test */
	public function it_strips_carriage_return_from_email_header_value(): void {
		$result = WPAPPT_Helper_Sanitizer::email_header_value( "Jane\rSmith" );

		$this->assertSame( 'JaneSmith', $result );
	}

	/** @test */
	public function it_strips_newline_from_email_header_value(): void {
		$result = WPAPPT_Helper_Sanitizer::email_header_value( "Jane\nSmith" );

		$this->assertSame( 'JaneSmith', $result );
	}

	/** @test */
	public function it_strips_crlf_injection_attempt_from_email_header_value(): void {
		$input  = "Jane Smith\r\nBcc: attacker@evil.com";
		$result = WPAPPT_Helper_Sanitizer::email_header_value( $input );

		$this->assertSame( 'Jane SmithBcc: attacker@evil.com', $result );
		$this->assertStringNotContainsString( "\r", $result );
		$this->assertStringNotContainsString( "\n", $result );
	}

	/** @test */
	public function it_leaves_clean_email_header_values_unchanged(): void {
		$this->assertSame( 'Jane Smith', WPAPPT_Helper_Sanitizer::email_header_value( 'Jane Smith' ) );
	}

	// =========================================================================
	// service_input
	// =========================================================================

	/** @test */
	public function service_input_casts_price_to_float_rounded_to_two_decimals(): void {
		$result = WPAPPT_Helper_Sanitizer::service_input( [ 'name' => 'A', 'duration_mins' => 60, 'price' => '49.999' ] );

		$this->assertSame( 50.0, $result['price'] );
	}

	/** @test */
	public function service_input_casts_duration_mins_to_int(): void {
		$result = WPAPPT_Helper_Sanitizer::service_input( [ 'name' => 'A', 'duration_mins' => '90.7', 'price' => 0 ] );

		$this->assertSame( 90, $result['duration_mins'] );
	}

	/** @test */
	public function service_input_is_active_is_one_when_key_present(): void {
		$result = WPAPPT_Helper_Sanitizer::service_input( [ 'name' => 'A', 'duration_mins' => 60, 'price' => 0, 'is_active' => 'anything' ] );

		$this->assertSame( 1, $result['is_active'] );
	}

	/** @test */
	public function service_input_is_active_is_zero_when_key_absent(): void {
		$result = WPAPPT_Helper_Sanitizer::service_input( [ 'name' => 'A', 'duration_mins' => 60, 'price' => 0 ] );

		$this->assertSame( 0, $result['is_active'] );
	}

	/** @test */
	public function service_input_returns_all_expected_keys(): void {
		$result = WPAPPT_Helper_Sanitizer::service_input( [] );

		$this->assertArrayHasKey( 'name',          $result );
		$this->assertArrayHasKey( 'duration_mins', $result );
		$this->assertArrayHasKey( 'price',         $result );
		$this->assertArrayHasKey( 'is_active',     $result );
		$this->assertArrayHasKey( 'sort_order',    $result );
	}

	// =========================================================================
	// booking_input
	// =========================================================================

	/** @test */
	public function booking_input_casts_service_id_to_int(): void {
		$result = WPAPPT_Helper_Sanitizer::booking_input( [ 'service_id' => '3abc' ] );

		$this->assertSame( 3, $result['service_id'] );
	}

	/** @test */
	public function booking_input_returns_all_expected_keys(): void {
		$result = WPAPPT_Helper_Sanitizer::booking_input( [] );

		$expected_keys = [
			'service_id', 'appointment_date', 'start_time',
			'customer_name', 'customer_email', 'customer_phone',
			'injury_notes', 'comments',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $result );
		}
	}

	/** @test */
	public function booking_input_uses_wp_kses_post_for_injury_notes(): void {
		Functions\expect( 'wp_kses_post' )
			->once()
			->with( '<script>bad</script>notes' )
			->andReturn( 'notes' );

		// Suppress other wp_kses_post calls from the 'comments' field.
		Functions\when( 'wp_kses_post' )->returnArg();

		$result = WPAPPT_Helper_Sanitizer::booking_input( [ 'injury_notes' => '<script>bad</script>notes' ] );

		$this->assertSame( 'notes', $result['injury_notes'] );
	}

	// =========================================================================
	// blocked_slot_input
	// =========================================================================

	/** @test */
	public function blocked_slot_input_converts_empty_reason_to_null(): void {
		$result = WPAPPT_Helper_Sanitizer::blocked_slot_input( [
			'blocked_date' => '2026-06-01',
			'start_time'   => '10:00',
			'end_time'     => '11:00',
			'reason'       => '',
		] );

		$this->assertNull( $result['reason'] );
	}

	/** @test */
	public function blocked_slot_input_keeps_non_empty_reason(): void {
		$result = WPAPPT_Helper_Sanitizer::blocked_slot_input( [
			'blocked_date' => '2026-06-01',
			'start_time'   => '10:00',
			'end_time'     => '11:00',
			'reason'       => 'School run',
		] );

		$this->assertSame( 'School run', $result['reason'] );
	}
}
