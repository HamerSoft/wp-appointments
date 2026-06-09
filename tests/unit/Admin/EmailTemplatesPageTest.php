<?php

namespace WpAppointments\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Admin_Email_Templates_Page;

/**
 * @covers WPAPPT_Admin_Email_Templates_Page
 */
class EmailTemplatesPageTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( '__' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_textarea_field' )->returnArg();
	}

	/** @test */
	public function save_posted_data_stores_sanitized_values_for_all_languages(): void {
		$stored = [];
		Functions\when( 'update_option' )->alias(
			function ( string $key, string $value ) use ( &$stored ): bool {
				$stored[ $key ] = $value;
				return true;
			}
		);

		$input = [
			'booking_confirmed' => [
				'en' => [
					'subject' => 'Custom EN Subject',
					'body'    => 'Custom EN Body',
					'closing' => 'Custom EN Closing',
				],
				'nl' => [
					'subject' => 'Aangepast NL Onderwerp',
					'body'    => 'Aangepast NL Tekst',
					'closing' => 'Aangepast NL Afsluiting',
				],
			],
		];

		( new WPAPPT_Admin_Email_Templates_Page() )->save_posted_data( $input );

		$this->assertSame( 'Custom EN Subject',       $stored['wpappt_tpl_booking_confirmed_en_subject'] );
		$this->assertSame( 'Custom EN Body',          $stored['wpappt_tpl_booking_confirmed_en_body'] );
		$this->assertSame( 'Custom EN Closing',       $stored['wpappt_tpl_booking_confirmed_en_closing'] );
		$this->assertSame( 'Aangepast NL Onderwerp',  $stored['wpappt_tpl_booking_confirmed_nl_subject'] );
		$this->assertSame( 'Aangepast NL Tekst',      $stored['wpappt_tpl_booking_confirmed_nl_body'] );
		$this->assertSame( 'Aangepast NL Afsluiting', $stored['wpappt_tpl_booking_confirmed_nl_closing'] );
	}

	/** @test */
	public function save_posted_data_ignores_unknown_slugs(): void {
		Functions\expect( 'update_option' )->never();

		( new WPAPPT_Admin_Email_Templates_Page() )->save_posted_data( [
			'totally_fake_template' => [
				'en' => [ 'subject' => 'Hack attempt' ],
			],
		] );
	}

	/** @test */
	public function save_posted_data_ignores_unknown_languages(): void {
		$stored = [];
		Functions\when( 'update_option' )->alias(
			function ( string $key, string $value ) use ( &$stored ): bool {
				$stored[ $key ] = $value;
				return true;
			}
		);

		( new WPAPPT_Admin_Email_Templates_Page() )->save_posted_data( [
			'booking_confirmed' => [
				'xx' => [ 'subject' => 'Unknown lang' ],  // invalid
				'en' => [ 'subject' => 'Valid EN' ],
			],
		] );

		$this->assertArrayNotHasKey( 'wpappt_tpl_booking_confirmed_xx_subject', $stored );
		$this->assertArrayHasKey( 'wpappt_tpl_booking_confirmed_en_subject', $stored );
	}

	/** @test */
	public function save_posted_data_ignores_unknown_fields(): void {
		$stored = [];
		Functions\when( 'update_option' )->alias(
			function ( string $key, string $value ) use ( &$stored ): bool {
				$stored[ $key ] = $value;
				return true;
			}
		);

		( new WPAPPT_Admin_Email_Templates_Page() )->save_posted_data( [
			'booking_confirmed' => [
				'en' => [
					'subject'        => 'OK',
					'injected_field' => 'Not saved',
				],
			],
		] );

		$this->assertArrayNotHasKey( 'wpappt_tpl_booking_confirmed_en_injected_field', $stored );
		$this->assertArrayHasKey( 'wpappt_tpl_booking_confirmed_en_subject', $stored );
	}

	/** @test */
	public function save_posted_data_saves_empty_string_when_field_is_missing_from_post(): void {
		$stored = [];
		Functions\when( 'update_option' )->alias(
			function ( string $key, string $value ) use ( &$stored ): bool {
				$stored[ $key ] = $value;
				return true;
			}
		);

		// Only 'subject' posted; 'body' and 'closing' missing.
		( new WPAPPT_Admin_Email_Templates_Page() )->save_posted_data( [
			'booking_confirmed' => [
				'en' => [ 'subject' => 'Only subject' ],
			],
		] );

		$this->assertSame( '',             $stored['wpappt_tpl_booking_confirmed_en_body'] );
		$this->assertSame( '',             $stored['wpappt_tpl_booking_confirmed_en_closing'] );
	}
}
