<?php
/**
 * Uninstall: drops all plugin tables.
 *
 * Only runs when the admin clicks "Delete" in Plugins > Installed Plugins.
 * Never runs on deactivation.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = [
	$wpdb->prefix . 'appointments_audit_log',   // drop dependents first
	$wpdb->prefix . 'appointments_bookings',
	$wpdb->prefix . 'appointments_blocked_slots',
	$wpdb->prefix . 'appointments_availability',
	$wpdb->prefix . 'appointments_services',
];

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are hardcoded.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// Remove all plugin options.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'wpappt_' ) . '%'
	)
);
