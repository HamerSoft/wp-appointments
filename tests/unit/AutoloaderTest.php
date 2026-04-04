<?php

namespace WpAppointments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPAPPT_Autoloader;

/**
 * @covers WPAPPT_Autoloader::resolve_path
 *
 * Pure PHP — no Brain Monkey or Mockery needed.
 * resolve_path() only does string manipulation against WPAPPT_PLUGIN_DIR.
 */
class AutoloaderTest extends TestCase {

	// =========================================================================
	// Returns null for non-plugin classes
	// =========================================================================

	/** @test */
	public function it_returns_null_for_classes_outside_the_plugin_namespace(): void {
		$this->assertNull( WPAPPT_Autoloader::resolve_path( 'SomeOtherClass' ) );
		$this->assertNull( WPAPPT_Autoloader::resolve_path( 'WP_List_Table' ) );
		$this->assertNull( WPAPPT_Autoloader::resolve_path( '' ) );
	}

	// =========================================================================
	// Top-level includes/
	// =========================================================================

	/** @test */
	public function it_resolves_top_level_plugin_class(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Plugin' );

		$this->assertStringEndsWith( 'includes/class-plugin.php', $path );
	}

	/** @test */
	public function it_resolves_activator_class(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Activator' );

		$this->assertStringEndsWith( 'includes/class-activator.php', $path );
	}

	/** @test */
	public function it_resolves_deactivator_class(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Deactivator' );

		$this->assertStringEndsWith( 'includes/class-deactivator.php', $path );
	}

	// =========================================================================
	// Model classes → includes/models/
	// =========================================================================

	/** @test */
	public function it_resolves_booking_model(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Model_Booking' );

		$this->assertStringEndsWith( 'includes/models/class-booking.php', $path );
	}

	/** @test */
	public function it_resolves_service_model(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Model_Service' );

		$this->assertStringEndsWith( 'includes/models/class-service.php', $path );
	}

	/** @test */
	public function it_resolves_availability_model(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Model_Availability' );

		$this->assertStringEndsWith( 'includes/models/class-availability.php', $path );
	}

	// =========================================================================
	// Controller classes → includes/controllers/
	// =========================================================================

	/** @test */
	public function it_resolves_admin_ajax_controller(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Controller_Admin_Ajax' );

		$this->assertStringEndsWith( 'includes/controllers/class-admin-ajax.php', $path );
	}

	/** @test */
	public function it_resolves_booking_controller(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Controller_Booking' );

		$this->assertStringEndsWith( 'includes/controllers/class-booking.php', $path );
	}

	/** @test */
	public function it_resolves_reschedule_controller(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Controller_Reschedule' );

		$this->assertStringEndsWith( 'includes/controllers/class-reschedule.php', $path );
	}

	// =========================================================================
	// Service classes → includes/services/
	// =========================================================================

	/** @test */
	public function it_resolves_email_service(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Service_Email' );

		$this->assertStringEndsWith( 'includes/services/class-email.php', $path );
	}

	/** @test */
	public function it_resolves_availability_service(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Service_Availability' );

		$this->assertStringEndsWith( 'includes/services/class-availability.php', $path );
	}

	/** @test */
	public function it_resolves_token_service(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Service_Token' );

		$this->assertStringEndsWith( 'includes/services/class-token.php', $path );
	}

	// =========================================================================
	// Helper classes → includes/helpers/
	// =========================================================================

	/** @test */
	public function it_resolves_sanitizer_helper(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Helper_Sanitizer' );

		$this->assertStringEndsWith( 'includes/helpers/class-sanitizer.php', $path );
	}

	// =========================================================================
	// Admin classes → admin/
	// =========================================================================

	/** @test */
	public function it_resolves_bare_admin_class(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Admin' );

		$this->assertStringEndsWith( 'admin/class-admin.php', $path );
	}

	/** @test */
	public function it_resolves_bookings_list_table(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Admin_Bookings_List_Table' );

		$this->assertStringEndsWith( 'admin/class-bookings-list-table.php', $path );
	}

	/** @test */
	public function it_resolves_settings_page(): void {
		$path = WPAPPT_Autoloader::resolve_path( 'WPAPPT_Admin_Settings_Page' );

		$this->assertStringEndsWith( 'admin/class-settings-page.php', $path );
	}

	// =========================================================================
	// Files that must already exist (catches regressions on file renames)
	// =========================================================================

	/**
	 * @test
	 * @dataProvider existing_classes_provider
	 */
	public function resolved_path_points_to_an_existing_file( string $class ): void {
		$path = WPAPPT_Autoloader::resolve_path( $class );

		$this->assertNotNull( $path );
		$this->assertFileExists(
			$path,
			"Expected file for {$class} does not exist at {$path}"
		);
	}

	public static function existing_classes_provider(): array {
		return [
			'Plugin'                    => [ 'WPAPPT_Plugin'                    ],
			'Activator'                 => [ 'WPAPPT_Activator'                 ],
			'Deactivator'               => [ 'WPAPPT_Deactivator'               ],
			'Autoloader'                => [ 'WPAPPT_Autoloader'                ],
			'Model_Booking'             => [ 'WPAPPT_Model_Booking'             ],
			'Model_Service'             => [ 'WPAPPT_Model_Service'             ],
			'Model_Availability'        => [ 'WPAPPT_Model_Availability'        ],
			'Helper_Sanitizer'          => [ 'WPAPPT_Helper_Sanitizer'          ],
			'Admin'                     => [ 'WPAPPT_Admin'                     ],
			'Admin_Bookings_List_Table' => [ 'WPAPPT_Admin_Bookings_List_Table' ],
			'Admin_Settings_Page'       => [ 'WPAPPT_Admin_Settings_Page'       ],
			'Controller_Admin_Ajax'     => [ 'WPAPPT_Controller_Admin_Ajax'     ],
			'Controller_Booking'        => [ 'WPAPPT_Controller_Booking'        ],
			'Controller_Reschedule'     => [ 'WPAPPT_Controller_Reschedule'     ],
			'Helper_Rate_Limiter'       => [ 'WPAPPT_Helper_Rate_Limiter'       ],
			'Rest_Api'                  => [ 'WPAPPT_Rest_Api'                  ],
			'Service_Availability'      => [ 'WPAPPT_Service_Availability'      ],
			'Service_Token'             => [ 'WPAPPT_Service_Token'             ],
		];
	}
}
