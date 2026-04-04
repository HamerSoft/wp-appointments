<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on plugin activation.
 *
 * Uses dbDelta() which is safe to call on an existing database:
 *   - Creates tables that do not yet exist.
 *   - Adds columns that are missing from existing tables.
 *   - Never drops tables, columns, or existing data.
 */
class WPAPPT_Activator {

	public static function activate(): void {
		self::create_tables();
		self::set_default_options();

		// Store the version so future activations can run targeted upgrades.
		update_option( 'wpappt_version', WPAPPT_VERSION );
	}

	// -------------------------------------------------------------------------
	// Table creation
	// -------------------------------------------------------------------------

	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		// dbDelta rules:
		//   - Two spaces before KEY definitions.
		//   - Each column/key on its own line.
		//   - No trailing comma on the last line before the closing parenthesis.

		$tables = [];

		// ------------------------------------------------------------------
		// Services
		// ------------------------------------------------------------------
		$tables[] = "CREATE TABLE {$wpdb->prefix}appointments_services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL DEFAULT '',
  duration_mins SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY is_active (is_active)
) {$charset};";

		// ------------------------------------------------------------------
		// Weekly availability template
		// ------------------------------------------------------------------
		$tables[] = "CREATE TABLE {$wpdb->prefix}appointments_availability (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  day_of_week TINYINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  label VARCHAR(80) NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY day_of_week (day_of_week)
) {$charset};";

		// ------------------------------------------------------------------
		// One-off blocked slots
		// ------------------------------------------------------------------
		$tables[] = "CREATE TABLE {$wpdb->prefix}appointments_blocked_slots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  blocked_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  reason VARCHAR(120) NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY blocked_date (blocked_date)
) {$charset};";

		// ------------------------------------------------------------------
		// Bookings
		// updated_at is managed in application code (set explicitly on UPDATE)
		// to avoid dbDelta issues with ON UPDATE CURRENT_TIMESTAMP.
		// ------------------------------------------------------------------
		$tables[] = "CREATE TABLE {$wpdb->prefix}appointments_bookings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  appointment_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  customer_name VARCHAR(120) NOT NULL DEFAULT '',
  customer_email VARCHAR(254) NOT NULL DEFAULT '',
  customer_phone VARCHAR(30) NOT NULL DEFAULT '',
  injury_notes TEXT NULL DEFAULT NULL,
  comments TEXT NULL DEFAULT NULL,
  reschedule_token VARCHAR(64) NULL DEFAULT NULL,
  token_expires_at DATETIME NULL DEFAULT NULL,
  admin_notes TEXT NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY appointment_date (appointment_date),
  KEY reschedule_token (reschedule_token),
  KEY customer_email (customer_email)
) {$charset};";

		// ------------------------------------------------------------------
		// Audit log — records every booking status transition
		// ------------------------------------------------------------------
		$tables[] = "CREATE TABLE {$wpdb->prefix}appointments_audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  old_status VARCHAR(20) NOT NULL DEFAULT '',
  new_status VARCHAR(20) NOT NULL DEFAULT '',
  actor VARCHAR(120) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY booking_id (booking_id)
) {$charset};";

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}

	// -------------------------------------------------------------------------
	// Default options — only set if not already present
	// -------------------------------------------------------------------------

	private static function set_default_options(): void {
		$defaults = [
			'wpappt_admin_email'   => get_option( 'admin_email' ),
			'wpappt_sender_name'   => get_bloginfo( 'name' ),
			'wpappt_booking_page'  => 0,
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}
}
