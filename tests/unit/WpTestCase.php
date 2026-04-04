<?php

namespace WpAppointments\Tests\Unit;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for all unit tests.
 *
 * Handles Brain Monkey setup/teardown (WordPress function mocks) and
 * Mockery cleanup via the MockeryPHPUnitIntegration trait.
 */
abstract class WpTestCase extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create a Mockery mock of wpdb with the plugin prefix preset.
	 *
	 * @return \Mockery\MockInterface&\wpdb
	 */
	protected function mockDb(): \Mockery\MockInterface {
		$db         = \Mockery::mock( 'wpdb' );
		$db->prefix = 'wp_';
		$db->insert_id  = 0;
		$db->last_error = '';

		return $db;
	}
}
