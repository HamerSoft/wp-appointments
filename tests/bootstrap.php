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
// WordPress constants
// ---------------------------------------------------------------------------

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

// Plugin rate-limiting constants (normally set in wp-appointments.php)
defined( 'WPAPPT_RATE_LIMIT'  ) || define( 'WPAPPT_RATE_LIMIT',  5 );
defined( 'WPAPPT_RATE_WINDOW' ) || define( 'WPAPPT_RATE_WINDOW', 10 * MINUTE_IN_SECONDS );

// ---------------------------------------------------------------------------
// WordPress class stubs
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $code    = '';
		public string $message = '';
		public array  $data    = [];

		public function __construct( string $code = '', string $message = '', array $data = [] ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string    { return $this->code; }
		public function get_error_message(): string { return $this->message; }
		public function get_error_data(): array     { return $this->data; }
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public mixed $data   = null;
		public int   $status = 200;

		public function __construct( mixed $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		public function get_status(): int  { return $this->status; }
		public function get_data(): mixed  { return $this->data; }
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params;

		public function __construct( array $params = [] ) {
			$this->params = $params;
		}

		public function get_params(): array                { return $this->params; }
		public function get_param( string $key ): mixed    { return $this->params[ $key ] ?? null; }
	}
}

// ---------------------------------------------------------------------------
// WordPress function stubs used outside Brain Monkey scope
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
