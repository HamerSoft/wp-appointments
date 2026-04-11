<?php

namespace WpAppointments\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Admin;

/**
 * @covers WPAPPT_Admin::display_notices
 */
class AdminTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();

		// WPAPPT_Admin's constructor creates model instances that read $wpdb.
		$wpdb            = \Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;

		// Stub WordPress functions used by the constructor dependencies and
		// by display_notices() itself.
		Functions\when( 'add_action'        )->justReturn( null );
		Functions\when( 'sanitize_key'      )->returnArg();
		Functions\when( '__'                )->returnArg();
		Functions\when( 'esc_attr'          )->returnArg();
		Functions\when( 'esc_html'          )->returnArg();
	}

	// =========================================================================
	// display_notices
	// =========================================================================

	/** @test */
	public function it_outputs_nothing_when_no_notice_query_arg_is_present(): void {
		$_GET = [];

		$screen     = \Mockery::mock( 'WP_Screen' );
		$screen->id = 'toplevel_page_wpappt-bookings';

		Functions\when( 'get_current_screen' )->justReturn( $screen );

		ob_start();
		( new WPAPPT_Admin() )->display_notices();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/** @test */
	public function it_outputs_nothing_for_an_unrecognised_notice_key(): void {
		$_GET = [ 'wpappt_notice' => 'unknown_key' ];

		$screen     = \Mockery::mock( 'WP_Screen' );
		$screen->id = 'toplevel_page_wpappt-bookings';

		Functions\when( 'get_current_screen' )->justReturn( $screen );

		ob_start();
		( new WPAPPT_Admin() )->display_notices();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/** @test */
	public function it_outputs_a_success_notice_for_booking_confirmed(): void {
		$_GET = [ 'wpappt_notice' => 'booking_confirmed' ];

		$screen     = \Mockery::mock( 'WP_Screen' );
		$screen->id = 'toplevel_page_wpappt-bookings';

		Functions\when( 'get_current_screen' )->justReturn( $screen );

		ob_start();
		( new WPAPPT_Admin() )->display_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( 'Booking confirmed.', $output );
	}

	/** @test */
	public function it_outputs_an_error_notice_for_error_nonce(): void {
		$_GET = [ 'wpappt_notice' => 'error_nonce' ];

		$screen     = \Mockery::mock( 'WP_Screen' );
		$screen->id = 'toplevel_page_wpappt-bookings';

		Functions\when( 'get_current_screen' )->justReturn( $screen );

		ob_start();
		( new WPAPPT_Admin() )->display_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'Security check failed.', $output );
	}

	/** @test */
	public function it_outputs_nothing_when_screen_is_not_a_plugin_screen(): void {
		$_GET = [ 'wpappt_notice' => 'booking_confirmed' ];

		$screen     = \Mockery::mock( 'WP_Screen' );
		$screen->id = 'edit-post'; // unrelated WP screen

		Functions\when( 'get_current_screen' )->justReturn( $screen );

		ob_start();
		( new WPAPPT_Admin() )->display_notices();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/** @test */
	public function it_passes_all_notice_messages_through_the_translation_function(): void {
		// Override the setUp() stub so __() prepends a marker. If the message
		// string goes through __() the marker will appear in the output,
		// proving it is not a hardcoded literal.
		Functions\when( '__' )->alias( function ( string $text ): string {
			return 'TRANSLATED:' . $text;
		} );

		$_GET = [ 'wpappt_notice' => 'service_saved' ];

		$screen     = \Mockery::mock( 'WP_Screen' );
		$screen->id = 'toplevel_page_wpappt-bookings';

		Functions\when( 'get_current_screen' )->justReturn( $screen );

		ob_start();
		( new WPAPPT_Admin() )->display_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'TRANSLATED:', $output );
	}

	protected function tearDown(): void {
		unset( $_GET['wpappt_notice'], $GLOBALS['wpdb'] );
		parent::tearDown();
	}
}
