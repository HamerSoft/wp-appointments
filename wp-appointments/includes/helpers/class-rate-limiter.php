<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transient-based fixed-window rate limiter.
 *
 * Each (action, IP) pair gets its own transient. The window starts on the
 * first request and expires after $window_seconds regardless of subsequent
 * requests — this is a fixed window, not a sliding one.
 *
 * Used by public REST endpoints to deter bot submissions.
 */
class WPAPPT_Helper_Rate_Limiter {

	/**
	 * Check whether the given IP is within the allowed limit for an action.
	 *
	 * Returns true  → request is allowed (counter incremented).
	 * Returns false → limit exceeded; caller should return HTTP 429.
	 *
	 * @param string $action         Short identifier, e.g. 'booking', 'reschedule'.
	 * @param string $ip             Client IP address.
	 * @param int    $max            Max allowed requests per window.
	 * @param int    $window_seconds Window length in seconds (default 10 minutes).
	 */
	public static function check(
		string $action,
		string $ip,
		int $max            = 5,
		int $window_seconds = 600
	): bool {
		$key  = 'wpappt_rl_' . sanitize_key( $action ) . '_' . substr( md5( $ip ), 0, 12 );
		$data = get_transient( $key );

		if ( false === $data ) {
			// First request in a new window.
			set_transient( $key, [ 'count' => 1, 'since' => time() ], $window_seconds );
			return true;
		}

		if ( (int) $data['count'] >= $max ) {
			return false;
		}

		// Increment the counter while preserving the original window expiry.
		$remaining = max( 1, $window_seconds - ( time() - (int) $data['since'] ) );
		set_transient(
			$key,
			[ 'count' => (int) $data['count'] + 1, 'since' => (int) $data['since'] ],
			$remaining
		);

		return true;
	}
}
