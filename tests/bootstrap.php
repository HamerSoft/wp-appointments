<?php
/**
 * PHPUnit bootstrap.
 *
 * Sets up WordPress constants, stub classes, and the plugin autoloader so
 * that plugin classes can be loaded without a real WordPress install.
 */

// ---------------------------------------------------------------------------
// WordPress constants needed by every plugin file
// ---------------------------------------------------------------------------

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// wpdb output-format constants
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

// Plugin-specific constants (normally set in wp-appointments.php)
define( 'WPAPPT_VERSION',    '1.0.0' );
define( 'WPAPPT_PLUGIN_DIR', dirname( __DIR__ ) . '/wp-appointments/' );
define( 'WPAPPT_PLUGIN_URL', 'http://localhost/wp-content/plugins/wp-appointments/' );
define( 'WPAPPT_PLUGIN_FILE', WPAPPT_PLUGIN_DIR . 'wp-appointments.php' );

// ---------------------------------------------------------------------------
// Composer autoloader (Brain Monkey, Mockery, PHPUnit)
// ---------------------------------------------------------------------------

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// WordPress class stubs
// ---------------------------------------------------------------------------

/**
 * Minimal wpdb stub — only the methods and properties the plugin models use.
 * Mockery extends this to set per-test expectations.
 */
if ( ! class_exists( 'wpdb' ) ) {
	class wpdb {
		public string $prefix     = 'wp_';
		public int    $insert_id  = 0;
		public string $last_error = '';

		public function prepare( string $query, mixed ...$args ): string {
			return $query;
		}

		public function get_results( string $query, ?string $output = null ): array {
			return [];
		}

		public function get_row( string $query, ?string $output = null ): mixed {
			return null;
		}

		public function get_var( string $query ): mixed {
			return null;
		}

		public function insert( string $table, array $data, array $format = [] ): int|false {
			return false;
		}

		public function update( string $table, array $data, array $where, array $format = [], array $where_format = [] ): int|false {
			return false;
		}

		public function delete( string $table, array $where, array $where_format = [] ): int|false {
			return false;
		}

		/** Mirrors WordPress behaviour: escapes % and _ for use in LIKE. */
		public function esc_like( string $text ): string {
			return addcslashes( $text, '_%\\' );
		}
	}
}

// ---------------------------------------------------------------------------
// Plugin autoloader — reuse the production class directly.
// WPAPPT_PLUGIN_DIR is already defined above so it resolves to the real paths.
// ---------------------------------------------------------------------------

require_once WPAPPT_PLUGIN_DIR . 'includes/class-autoloader.php';
WPAPPT_Autoloader::register();
