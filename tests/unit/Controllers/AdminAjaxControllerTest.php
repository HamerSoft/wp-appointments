<?php

namespace WpAppointments\Tests\Unit\Controllers;

use Brain\Monkey\Functions;
use WpAppointments\Tests\Unit\WpTestCase;
use WPAPPT_Controller_Admin_Ajax;

/**
 * @covers WPAPPT_Controller_Admin_Ajax
 */
class AdminAjaxControllerTest extends WpTestCase {

	private function callResolveAttachment( int $id ): array {
		// Ensure global $wpdb exists so model constructors don't fail.
		global $wpdb;
		if ( ! $wpdb ) {
			$wpdb         = new \wpdb();
			$wpdb->prefix = 'wp_';
		}

		$controller = new WPAPPT_Controller_Admin_Ajax();
		$method     = new \ReflectionMethod( $controller, 'resolve_attachment' );
		$method->setAccessible( true );
		return $method->invoke( $controller, $id );
	}

	// =========================================================================
	// resolve_attachment()
	// =========================================================================

	/** @test */
	public function resolve_attachment_returns_empty_array_for_zero_id(): void {
		$result = $this->callResolveAttachment( 0 );
		$this->assertSame( [], $result );
	}

	/** @test */
	public function resolve_attachment_returns_empty_array_when_post_not_found(): void {
		Functions\when( 'get_post' )->justReturn( null );
		$result = $this->callResolveAttachment( 42 );
		$this->assertSame( [], $result );
	}

	/** @test */
	public function resolve_attachment_returns_empty_array_for_non_attachment_post(): void {
		Functions\when( 'get_post' )->justReturn( (object) [ 'post_type' => 'post' ] );
		$result = $this->callResolveAttachment( 42 );
		$this->assertSame( [], $result );
	}

	/** @test */
	public function resolve_attachment_returns_empty_array_when_file_missing_on_disk(): void {
		$missing = sys_get_temp_dir() . '/wpappt_nonexistent_' . uniqid() . '.pdf';
		Functions\when( 'get_post' )->justReturn( (object) [ 'post_type' => 'attachment' ] );
		Functions\when( 'get_attached_file' )->justReturn( $missing );
		$result = $this->callResolveAttachment( 42 );
		$this->assertSame( [], $result );
	}

	/** @test */
	public function resolve_attachment_returns_path_array_for_valid_attachment(): void {
		$tmp = tempnam( sys_get_temp_dir(), 'wpappt_' );
		Functions\when( 'get_post' )->justReturn( (object) [ 'post_type' => 'attachment' ] );
		Functions\when( 'get_attached_file' )->justReturn( $tmp );
		$result = $this->callResolveAttachment( 42 );
		unlink( $tmp );
		$this->assertSame( [ $tmp ], $result );
	}
}
