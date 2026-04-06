<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on plugin deactivation.
 *
 * Deactivation is reversible — tables and data are intentionally preserved.
 * Only transient/scheduled-event cleanup happens here.
 */
class WPAPPT_Deactivator {

	public static function deactivate(): void {
		// Remove any scheduled cron events registered by this plugin.
		$hooks = [
			'wpappt_expire_tokens',
			'wpappt_send_reminders',
		];

		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}

		// Flush any plugin-specific transients (rate-limit counters, etc.).
		// We use a wildcard delete via the options table because WP has no
		// native bulk-transient deletion API.
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				  WHERE option_name LIKE %s
				     OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_wpappt_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_wpappt_' ) . '%'
			)
		);
	}
}
