<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drives the weekly token-expiry cleanup cron job.
 *
 * Nulls out reschedule_token and token_expires_at on any booking where the
 * token has already expired. Expired tokens are harmless — the validator
 * rejects them — but removing them keeps the table tidy and ensures no
 * long-stored token can ever be matched against a future booking by accident.
 */
class WPAPPT_Service_Token_Cleanup {

	private $db;
	private string $table;

	public function __construct( $db = null ) {
		global $wpdb;
		$this->db    = $db ?? $wpdb;
		$this->table = $this->db->prefix . 'appointments_bookings';
	}

	public function init(): void {
		add_action( 'wpappt_expire_tokens', [ $this, 'run' ] );
	}

	/**
	 * Called by WP-Cron weekly.
	 *
	 * Returns the number of rows updated (useful for testing and logging).
	 */
	public function run(): int {
		$this->db->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"UPDATE {$this->table}
			    SET reschedule_token = NULL,
			        token_expires_at = NULL
			  WHERE token_expires_at < NOW()"
		);

		return (int) $this->db->rows_affected;
	}
}
