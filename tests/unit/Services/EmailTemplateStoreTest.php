<?php

namespace WpAppointments\Tests\Unit\Services;

use Brain\Monkey\Functions;
use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Service_Email_Template_Store;

/**
 * @covers WPAPPT_Service_Email_Template_Store
 */
class EmailTemplateStoreTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( '' );
	}

	/** @test */
	public function get_registry_returns_all_eight_templates(): void {
		$registry = WPAPPT_Service_Email_Template_Store::get_registry();

		$this->assertCount( 8, $registry );
		$this->assertArrayHasKey( 'booking_received_customer', $registry );
		$this->assertArrayHasKey( 'booking_confirmed', $registry );
		$this->assertArrayHasKey( 'booking_cancelled', $registry );
		$this->assertArrayHasKey( 'reschedule_customer', $registry );
		$this->assertArrayHasKey( 'reminder_customer', $registry );
		$this->assertArrayHasKey( 'followup', $registry );
		$this->assertArrayHasKey( 'booking_received_admin', $registry );
		$this->assertArrayHasKey( 'reschedule_admin', $registry );
	}

	/** @test */
	public function each_registry_entry_has_label_and_fields(): void {
		foreach ( WPAPPT_Service_Email_Template_Store::get_registry() as $slug => $config ) {
			$this->assertArrayHasKey( 'label', $config, "Missing 'label' for {$slug}" );
			$this->assertArrayHasKey( 'fields', $config, "Missing 'fields' for {$slug}" );
			$this->assertNotEmpty( $config['fields'], "Empty fields for {$slug}" );
		}
	}

	/** @test */
	public function customer_templates_have_subject_body_and_closing(): void {
		$customer_slugs = [
			'booking_received_customer',
			'booking_confirmed',
			'booking_cancelled',
			'reschedule_customer',
			'reminder_customer',
			'followup',
		];
		$registry = WPAPPT_Service_Email_Template_Store::get_registry();
		foreach ( $customer_slugs as $slug ) {
			$fields = $registry[ $slug ]['fields'];
			$this->assertArrayHasKey( 'subject', $fields, "Missing 'subject' for {$slug}" );
			$this->assertArrayHasKey( 'body',    $fields, "Missing 'body' for {$slug}" );
			$this->assertArrayHasKey( 'closing', $fields, "Missing 'closing' for {$slug}" );
		}
	}

	/** @test */
	public function admin_templates_have_subject_and_body_but_no_closing(): void {
		$registry = WPAPPT_Service_Email_Template_Store::get_registry();

		$this->assertArrayHasKey( 'subject', $registry['booking_received_admin']['fields'] );
		$this->assertArrayHasKey( 'body',    $registry['booking_received_admin']['fields'] );
		$this->assertArrayNotHasKey( 'closing', $registry['booking_received_admin']['fields'] );

		$this->assertArrayHasKey( 'subject', $registry['reschedule_admin']['fields'] );
		$this->assertArrayHasKey( 'body',    $registry['reschedule_admin']['fields'] );
		$this->assertArrayNotHasKey( 'closing', $registry['reschedule_admin']['fields'] );
	}

	/** @test */
	public function get_texts_returns_hardcoded_defaults_when_options_are_empty(): void {
		// get_option is already stubbed to return '' in setUp().
		$texts = WPAPPT_Service_Email_Template_Store::get_texts( 'booking_confirmed', 'en' );

		$this->assertSame(
			'[{{site_name}}] Your booking is confirmed',
			$texts['subject']
		);
		$this->assertStringContainsString( 'Great news', $texts['body'] );
		$this->assertStringContainsString( 'If you have any questions', $texts['closing'] );
	}

	/** @test */
	public function get_texts_returns_stored_override_when_option_is_set(): void {
		Functions\when( 'get_option' )->alias( function ( string $key ) {
			if ( $key === 'wpappt_tpl_booking_confirmed_en_subject' ) {
				return 'Uw afspraak is bevestigd';
			}
			return '';
		} );

		$texts = WPAPPT_Service_Email_Template_Store::get_texts( 'booking_confirmed', 'en' );

		$this->assertSame( 'Uw afspraak is bevestigd', $texts['subject'] );
		// Other fields still fall back to defaults.
		$this->assertStringContainsString( 'Great news', $texts['body'] );
	}

	/** @test */
	public function get_texts_returns_nl_override_independently_of_en(): void {
		Functions\when( 'get_option' )->alias( function ( string $key ) {
			if ( $key === 'wpappt_tpl_booking_confirmed_nl_body' ) {
				return 'Geweldig — uw afspraak is bevestigd!';
			}
			return '';
		} );

		$nl_texts = WPAPPT_Service_Email_Template_Store::get_texts( 'booking_confirmed', 'nl' );
		$en_texts = WPAPPT_Service_Email_Template_Store::get_texts( 'booking_confirmed', 'en' );

		$this->assertSame( 'Geweldig — uw afspraak is bevestigd!', $nl_texts['body'] );
		$this->assertStringContainsString( 'Great news', $en_texts['body'] );
	}

	/** @test */
	public function get_texts_returns_empty_array_for_unknown_slug(): void {
		$texts = WPAPPT_Service_Email_Template_Store::get_texts( 'nonexistent_slug', 'en' );
		$this->assertSame( [], $texts );
	}

	// =========================================================================
	// customer_facing flag
	// =========================================================================

	/** @test */
	public function customer_facing_templates_have_flag_set_to_true(): void {
		$registry = WPAPPT_Service_Email_Template_Store::get_registry();
		$customer_slugs = [
			'booking_received_customer',
			'booking_confirmed',
			'booking_cancelled',
			'reschedule_customer',
			'reminder_customer',
			'followup',
		];
		foreach ( $customer_slugs as $slug ) {
			$this->assertTrue(
				$registry[ $slug ]['customer_facing'] ?? false,
				"Expected customer_facing = true for {$slug}"
			);
		}
	}

	/** @test */
	public function admin_templates_do_not_have_customer_facing_flag(): void {
		$registry = WPAPPT_Service_Email_Template_Store::get_registry();
		$this->assertEmpty( $registry['booking_received_admin']['customer_facing'] ?? null );
		$this->assertEmpty( $registry['reschedule_admin']['customer_facing'] ?? null );
	}

	// =========================================================================
	// get_default_attachment_path
	// =========================================================================

	/** @test */
	public function get_default_attachment_path_returns_empty_when_option_is_zero(): void {
		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
			if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' ) {
				return 0;
			}
			return $default ?? '';
		} );

		$path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
			'booking_confirmed', 'en'
		);

		$this->assertSame( '', $path );
	}

	/** @test */
	public function get_default_attachment_path_returns_empty_when_get_attached_file_returns_false(): void {
		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
			if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' ) {
				return 42;
			}
			return $default ?? '';
		} );
		Functions\when( 'get_post' )->justReturn( (object) [ 'post_type' => 'attachment' ] );
		Functions\when( 'get_attached_file' )->justReturn( false );

		$path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
			'booking_confirmed', 'en'
		);

		$this->assertSame( '', $path );
	}

	/** @test */
	public function get_default_attachment_path_returns_empty_when_file_does_not_exist_on_disk(): void {
		$missing = sys_get_temp_dir() . '/wpappt_nonexistent_' . uniqid() . '.pdf';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
			if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' ) {
				return 42;
			}
			return $default ?? '';
		} );
		Functions\when( 'get_post' )->justReturn( (object) [ 'post_type' => 'attachment' ] );
		Functions\when( 'get_attached_file' )->justReturn( $missing );

		$path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
			'booking_confirmed', 'en'
		);

		$this->assertSame( '', $path );
	}

	/** @test */
	public function get_default_attachment_path_returns_file_path_when_attachment_is_valid(): void {
		$tmp = tempnam( sys_get_temp_dir(), 'wpappt_' );

		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) use ( $tmp ) {
			if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' ) {
				return 42;
			}
			return $default ?? '';
		} );
		Functions\when( 'get_post' )->justReturn( (object) [ 'post_type' => 'attachment' ] );
		Functions\when( 'get_attached_file' )->justReturn( $tmp );

		$path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
			'booking_confirmed', 'en'
		);

		unlink( $tmp );

		$this->assertSame( $tmp, $path );
	}

	/** @test */
	public function get_default_attachment_path_returns_empty_when_post_is_not_an_attachment(): void {
		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
			if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' ) {
				return 42;
			}
			return $default ?? '';
		} );
		Functions\when( 'get_post' )->justReturn( (object) [ 'post_type' => 'page' ] );

		$path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
			'booking_confirmed', 'en'
		);

		$this->assertSame( '', $path );
	}

	/** @test */
	public function get_default_attachment_path_returns_empty_for_nl_when_only_en_is_set(): void {
		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
			// Only EN attachment is set; NL option returns 0.
			if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' ) return 42;
			if ( $key === 'wpappt_tpl_booking_confirmed_nl_attachment_id' ) return 0;
			return $default ?? '';
		} );

		$path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
			'booking_confirmed', 'nl'
		);

		$this->assertSame( '', $path );
	}
}
