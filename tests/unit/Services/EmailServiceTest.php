<?php

namespace WpAppointments\Tests\Unit\Services;

use Brain\Monkey\Functions;
use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Service_Email;
use WPAPPT_Model_Booking;
use WPAPPT_Model_Service;

/**
 * @covers WPAPPT_Service_Email
 */
class EmailServiceTest extends WpTestCase {

	// -------------------------------------------------------------------------
	// Fixtures
	// -------------------------------------------------------------------------

	private function makeService(
		?\Mockery\MockInterface $booking_model = null,
		?\Mockery\MockInterface $service_model = null
	): WPAPPT_Service_Email {
		return new WPAPPT_Service_Email(
			$booking_model ?? \Mockery::mock( WPAPPT_Model_Booking::class ),
			$service_model ?? \Mockery::mock( WPAPPT_Model_Service::class )
		);
	}

	private function fakeBooking( array $overrides = [] ): array {
		return array_merge( [
			'id'               => 1,
			'service_id'       => 2,
			'status'           => 'pending',
			'appointment_date' => '2026-07-01',
			'start_time'       => '10:00:00',
			'end_time'         => '11:00:00',
			'customer_name'    => 'Jane Doe',
			'customer_email'   => 'jane@example.com',
			'customer_phone'   => '0612345678',
			'injury_notes'     => '',
			'comments'         => '',
			'admin_notes'      => '',
			'reschedule_token' => null,
			'token_expires_at' => null,
		], $overrides );
	}

	private function fakeService(): array {
		return [ 'id' => 2, 'name' => 'Swedish Massage', 'duration_mins' => 60 ];
	}

	protected function setUp(): void {
		parent::setUp();

		// Stubs for WP functions used across the service and templates.
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Spa' );
		Functions\when( 'get_option' )->justReturn( 'admin@example.com' );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/' );
		Functions\when( 'get_permalink' )->justReturn( 'http://example.com/book/' );
		Functions\when( 'home_url' )->justReturn( 'http://example.com/' );
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'error_log' )->justReturn( null );
		Functions\when( 'nl2br' )->returnArg();
		Functions\when( 'esc_html_e' )->justReturn( null );
	}

	// =========================================================================
	// Routing: correct recipient
	// =========================================================================

	/** @test */
	public function send_booking_received_customer_sends_to_customer_email(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		Functions\expect( 'wp_mail' )
			->once()
			->withArgs( function ( string $to ): bool {
				return $to === 'jane@example.com';
			} )
			->andReturn( true );

		$this->makeService( $booking_model, $service_model )
		     ->send_booking_received_customer( 1 );
	}

	/** @test */
	public function send_booking_received_admin_sends_to_admin_email(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		Functions\expect( 'wp_mail' )
			->once()
			->withArgs( function ( string $to ): bool {
				// get_option is mocked to return 'admin@example.com'.
				return $to === 'admin@example.com';
			} )
			->andReturn( true );

		$this->makeService( $booking_model, $service_model )
		     ->send_booking_received_admin( 1 );
	}

	// =========================================================================
	// Graceful failure
	// =========================================================================

	/** @test */
	public function send_returns_false_when_booking_not_found(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'find' )->andReturn( null );

		Functions\expect( 'wp_mail' )->never();

		$result = $this->makeService( $booking_model )
		               ->send_booking_received_customer( 999 );

		$this->assertFalse( $result );
	}

	/** @test */
	public function send_followup_returns_false_when_booking_not_found(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$booking_model->shouldReceive( 'find' )->andReturn( null );

		Functions\expect( 'wp_mail' )->never();

		$result = $this->makeService( $booking_model )->send_followup( 999, 'Hello!' );

		$this->assertFalse( $result );
	}

	// =========================================================================
	// Email header security
	// =========================================================================

	/** @test */
	public function headers_include_html_content_type(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		$capturedHeaders = null;
		Functions\expect( 'wp_mail' )
			->once()
			->withArgs( function ( $to, $subject, $message, $headers ) use ( &$capturedHeaders ): bool {
				$capturedHeaders = $headers;
				return true;
			} )
			->andReturn( true );

		$this->makeService( $booking_model, $service_model )
		     ->send_booking_received_customer( 1 );

		$headers_str = implode( "\n", (array) $capturedHeaders );
		$this->assertStringContainsString( 'text/html', $headers_str );
	}

	/** @test */
	public function from_header_strips_newlines_from_sender_name(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		// Simulate a sender name containing an injected newline.
		Functions\when( 'get_option' )->alias( function ( string $key ) {
			if ( $key === 'wpappt_sender_name' ) {
				return "Test Spa\r\nBcc: hacker@evil.com";
			}
			return 'admin@example.com';
		} );

		$capturedHeaders = null;
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $message, $headers ) use ( &$capturedHeaders ): bool {
				$capturedHeaders = $headers;
				return true;
			}
		);

		$this->makeService( $booking_model, $service_model )
		     ->send_booking_received_customer( 1 );

		$from_header = '';
		foreach ( (array) $capturedHeaders as $header ) {
			if ( str_starts_with( $header, 'From:' ) ) {
				$from_header = $header;
				break;
			}
		}

		$this->assertStringNotContainsString( "\r\n", $from_header,
			'Newlines must be stripped from sender name to prevent email header injection'
		);
		$this->assertStringNotContainsString( "\r\nBcc:", $from_header,
			'Injected Bcc header must not appear as a separate header line'
		);
	}

	// =========================================================================
	// Hook registration
	// =========================================================================

	/** @test */
	public function init_registers_booking_created_hook(): void {
		Functions\expect( 'add_action' )
			->atLeast()->once()
			->withArgs( function ( string $hook ): bool {
				return $hook === 'wpappt_booking_created';
			} );

		// Catch-all for the other add_action calls in init().
		// Must be a second expect (not when) because Brain Monkey forbids when() after expect().
		Functions\expect( 'add_action' )->zeroOrMoreTimes()->andReturn( true );

		$this->makeService()->init();
	}

	/** @test */
	public function init_registers_status_changed_hook(): void {
		Functions\expect( 'add_action' )
			->atLeast()->once()
			->withArgs( function ( string $hook ): bool {
				return $hook === 'wpappt_booking_status_changed';
			} );

		Functions\expect( 'add_action' )->zeroOrMoreTimes()->andReturn( true );

		$this->makeService()->init();
	}

	/** @test */
	public function init_registers_booking_confirmed_hook(): void {
		Functions\expect( 'add_action' )
			->atLeast()->once()
			->withArgs( function ( string $hook ): bool {
				return $hook === 'wpappt_booking_confirmed';
			} );

		Functions\expect( 'add_action' )->zeroOrMoreTimes()->andReturn( true );

		$this->makeService()->init();
	}

	/** @test */
	public function init_registers_booking_rescheduled_hook(): void {
		Functions\expect( 'add_action' )
			->atLeast()->once()
			->withArgs( function ( string $hook ): bool {
				return $hook === 'wpappt_booking_rescheduled';
			} );

		Functions\expect( 'add_action' )->zeroOrMoreTimes()->andReturn( true );

		$this->makeService()->init();
	}

	// =========================================================================
	// on_status_changed routing
	// =========================================================================

	/** @test */
	public function on_booking_created_sends_both_customer_and_admin_emails(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->twice()->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->twice()->andReturn( $this->fakeService() );

		// Two wp_mail calls expected: one to customer, one to admin.
		Functions\expect( 'wp_mail' )->twice()->andReturn( true );

		$this->makeService( $booking_model, $service_model )->on_booking_created( 1 );
	}

	/** @test */
	public function on_status_changed_does_not_send_email_for_confirmed_status(): void {
		// 'confirmed' is now routed through on_booking_confirmed() (fired by the
		// token service) so on_status_changed must be a no-op for that status.
		Functions\expect( 'wp_mail' )->never();

		$this->makeService()->on_status_changed( 1, 'confirmed' );
	}

	/** @test */
	public function on_status_changed_sends_no_email_for_unknown_status(): void {
		Functions\expect( 'wp_mail' )->never();

		$this->makeService()->on_status_changed( 1, 'unknown_status' );
	}

	/** @test */
	public function on_booking_confirmed_sends_confirmation_to_customer(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		Functions\expect( 'wp_mail' )
			->once()
			->withArgs( function ( string $to ): bool {
				return $to === 'jane@example.com';
			} )
			->andReturn( true );

		$this->makeService( $booking_model, $service_model )
		     ->on_booking_confirmed( 1, 'http://example.com/book/?reschedule=abc123' );
	}

	/** @test */
	public function on_rescheduled_sends_customer_and_admin_emails(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		// Two emails: reschedule-customer (to customer) + reschedule-admin (to admin).
		$booking_model->shouldReceive( 'find' )->twice()->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->twice()->andReturn( $this->fakeService() );

		Functions\expect( 'wp_mail' )->twice()->andReturn( true );

		$this->makeService( $booking_model, $service_model )
		     ->on_rescheduled( 1, 'http://example.com/book/?reschedule=xyz' );
	}

	// =========================================================================
	// Attachment forwarding
	// =========================================================================

	/** @test */
	public function send_booking_confirmed_passes_attachments_as_5th_arg_to_wp_mail(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		$capturedAttachments = 'not-set';
		Functions\expect( 'wp_mail' )
			->once()
			->withArgs( function ( $to, $subject, $body, $headers, $attachments ) use ( &$capturedAttachments ): bool {
				$capturedAttachments = $attachments;
				return true;
			} )
			->andReturn( true );

		$this->makeService( $booking_model, $service_model )
		     ->send_booking_confirmed( 1, '', [ '/tmp/intake.pdf' ] );

		$this->assertSame( [ '/tmp/intake.pdf' ], $capturedAttachments );
	}

	/** @test */
	public function send_booking_confirmed_passes_empty_attachments_by_default(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		$capturedAttachments = 'not-set';
		Functions\expect( 'wp_mail' )
			->once()
			->withArgs( function ( $to, $subject, $body, $headers, $attachments ) use ( &$capturedAttachments ): bool {
				$capturedAttachments = $attachments;
				return true;
			} )
			->andReturn( true );

		$this->makeService( $booking_model, $service_model )
		     ->send_booking_confirmed( 1 );

		$this->assertSame( [], $capturedAttachments );
	}

	/** @test */
	public function send_booking_cancelled_passes_attachments_as_5th_arg_to_wp_mail(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		$capturedAttachments = 'not-set';
		Functions\expect( 'wp_mail' )
			->once()
			->withArgs( function ( $to, $subject, $body, $headers, $attachments ) use ( &$capturedAttachments ): bool {
				$capturedAttachments = $attachments;
				return true;
			} )
			->andReturn( true );

		$this->makeService( $booking_model, $service_model )
		     ->send_booking_cancelled( 1, [ '/tmp/intake.pdf' ] );

		$this->assertSame( [ '/tmp/intake.pdf' ], $capturedAttachments );
	}

	/** @test */
	public function send_followup_passes_attachments_as_5th_arg_to_wp_mail(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		$capturedAttachments = 'not-set';
		Functions\expect( 'wp_mail' )
			->once()
			->withArgs( function ( $to, $subject, $body, $headers, $attachments ) use ( &$capturedAttachments ): bool {
				$capturedAttachments = $attachments;
				return true;
			} )
			->andReturn( true );

		$this->makeService( $booking_model, $service_model )
		     ->send_followup( 1, 'Hi Jane!', [ '/tmp/intake.pdf' ] );

		$this->assertSame( [ '/tmp/intake.pdf' ], $capturedAttachments );
	}

	// =========================================================================
	// Template store integration
	// =========================================================================

	/** @test */
	public function send_booking_confirmed_uses_subject_override_from_template_store(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
			if ( $key === 'wpappt_email_language' )                          return 'en';
			if ( $key === 'wpappt_tpl_booking_confirmed_en_subject' )        return 'Custom Subject Override';
			if ( $key === 'wpappt_sender_name' )                             return 'Test Spa';
			if ( $key === 'wpappt_admin_email' )                             return 'admin@example.com';
			return $default ?? '';
		} );

		$capturedSubject = null;
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedSubject ): bool {
				$capturedSubject = $subject;
				return true;
			}
		);

		$this->makeService( $booking_model, $service_model )->send_booking_confirmed( 1 );

		$this->assertSame( 'Custom Subject Override', $capturedSubject );
	}

	/** @test */
	public function send_booking_confirmed_uses_nl_subject_when_language_is_nl(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
			if ( $key === 'wpappt_email_language' )                          return 'nl';
			if ( $key === 'wpappt_tpl_booking_confirmed_nl_subject' )        return 'Uw afspraak is bevestigd';
			if ( $key === 'wpappt_sender_name' )                             return 'Test Spa';
			if ( $key === 'wpappt_admin_email' )                             return 'admin@example.com';
			return $default ?? '';
		} );

		$capturedSubject = null;
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedSubject ): bool {
				$capturedSubject = $subject;
				return true;
			}
		);

		$this->makeService( $booking_model, $service_model )->send_booking_confirmed( 1 );

		$this->assertSame( 'Uw afspraak is bevestigd', $capturedSubject );
	}

	/** @test */
	public function send_booking_confirmed_falls_back_to_en_for_invalid_language(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
			if ( $key === 'wpappt_email_language' ) return 'xx'; // invalid — must fall back to 'en'
			if ( $key === 'wpappt_sender_name' )    return 'Test Spa';
			if ( $key === 'wpappt_admin_email' )    return 'admin@example.com';
			return $default ?? '';
		} );

		$capturedSubject = null;
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedSubject ): bool {
				$capturedSubject = $subject;
				return true;
			}
		);

		$result = $this->makeService( $booking_model, $service_model )->send_booking_confirmed( 1 );

		$this->assertTrue( $result );
		// EN default subject with {{site_name}} substituted — proves get_lang() fell back to 'en'.
		$this->assertSame( '[Test Spa] Your booking is confirmed', $capturedSubject );
	}

	/** @test */
	public function apply_template_texts_substitutes_site_name_token_in_subject(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
			if ( $key === 'wpappt_email_language' )                               return 'en';
			if ( $key === 'wpappt_sender_name' )                                  return 'Test Spa';
			if ( $key === 'wpappt_admin_email' )                                  return 'admin@example.com';
			return $default ?? '';
		} );
		// get_bloginfo('name') → 'Test Spa' is already stubbed in setUp().

		$capturedSubject = null;
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedSubject ): bool {
				$capturedSubject = $subject;
				return true;
			}
		);

		$this->makeService( $booking_model, $service_model )->send_booking_confirmed( 1 );

		// Default subject = '[{{site_name}}] Your booking is confirmed'
		// After substitution with site_name = 'Test Spa':
		$this->assertSame( '[Test Spa] Your booking is confirmed', $capturedSubject );
	}

	// =========================================================================
	// Default attachment fallback (resolve_attachments behaviour)
	// =========================================================================

	/** @test */
	public function manual_attachment_is_used_and_default_is_not_fetched_when_manual_is_set(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
			if ( $key === 'wpappt_email_language' )  return 'en';
			if ( $key === 'wpappt_sender_name' )     return 'Test Spa';
			if ( $key === 'wpappt_admin_email' )     return 'admin@example.com';
			return $default ?? '';
		} );

		// get_attached_file must never be called — manual replaces default.
		Functions\expect( 'get_attached_file' )->never();

		$capturedAttachments = null;
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedAttachments ): bool {
				$capturedAttachments = $attachments;
				return true;
			}
		);

		$this->makeService( $booking_model, $service_model )
		     ->send_booking_confirmed( 1, '', [ '/tmp/manual.pdf' ] );

		$this->assertSame( [ '/tmp/manual.pdf' ], $capturedAttachments );
	}

	/** @test */
	public function default_attachment_is_used_when_no_manual_attachment_is_provided(): void {
		$tmp = tempnam( sys_get_temp_dir(), 'wpappt_default_' );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) use ( $tmp ) {
			if ( $key === 'wpappt_email_language' )                              return 'en';
			if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' )     return 99;
			if ( $key === 'wpappt_sender_name' )                                 return 'Test Spa';
			if ( $key === 'wpappt_admin_email' )                                 return 'admin@example.com';
			return $default ?? '';
		} );
		Functions\when( 'get_post' )->alias( function ( int $id ) {
			$post             = new \stdClass();
			$post->post_type  = 'attachment';
			return $post;
		} );
		Functions\when( 'get_attached_file' )->justReturn( $tmp );

		$capturedAttachments = null;
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedAttachments ): bool {
				$capturedAttachments = $attachments;
				return true;
			}
		);

		$this->makeService( $booking_model, $service_model )
		     ->send_booking_confirmed( 1 );

		unlink( $tmp );

		$this->assertSame( [ $tmp ], $capturedAttachments );
	}

	/** @test */
	public function no_attachment_is_used_when_neither_manual_nor_default_is_configured(): void {
		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
			if ( $key === 'wpappt_email_language' )                              return 'en';
			if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' )     return 0;
			if ( $key === 'wpappt_sender_name' )                                 return 'Test Spa';
			if ( $key === 'wpappt_admin_email' )                                 return 'admin@example.com';
			return $default ?? '';
		} );

		$capturedAttachments = null;
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedAttachments ): bool {
				$capturedAttachments = $attachments;
				return true;
			}
		);

		$this->makeService( $booking_model, $service_model )
		     ->send_booking_confirmed( 1 );

		$this->assertSame( [], $capturedAttachments );
	}

	/** @test */
	public function default_attachment_is_used_for_reminder_which_has_no_manual_attachment(): void {
		$tmp = tempnam( sys_get_temp_dir(), 'wpappt_reminder_' );

		$booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
		$service_model = \Mockery::mock( WPAPPT_Model_Service::class );

		$booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
		$service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

		Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) use ( $tmp ) {
			if ( $key === 'wpappt_email_language' )                             return 'en';
			if ( $key === 'wpappt_tpl_reminder_customer_en_attachment_id' )    return 55;
			if ( $key === 'wpappt_sender_name' )                                return 'Test Spa';
			if ( $key === 'wpappt_admin_email' )                                return 'admin@example.com';
			return $default ?? '';
		} );
		Functions\when( 'get_post' )->alias( function ( int $id ) {
			$post             = new \stdClass();
			$post->post_type  = 'attachment';
			return $post;
		} );
		Functions\when( 'get_attached_file' )->justReturn( $tmp );

		$capturedAttachments = null;
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedAttachments ): bool {
				$capturedAttachments = $attachments;
				return true;
			}
		);

		$this->makeService( $booking_model, $service_model )
		     ->send_reminder_customer( 1 );

		unlink( $tmp );

		$this->assertSame( [ $tmp ], $capturedAttachments );
	}
}
