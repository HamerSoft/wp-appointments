<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates, validates, and rotates customer reschedule tokens.
 *
 * Security model:
 *   - Raw token (64 hex chars from 32 random bytes) travels only in the email.
 *   - SHA-256 hash of the raw token is stored in the database — the hash can
 *     never be reversed to reconstruct the plaintext link.
 *   - Validation uses hash_equals() to prevent timing-side-channel attacks.
 *   - DB query enforces IS NOT NULL + token_expires_at > NOW() so nulled or
 *     expired tokens silently fail (no timing oracle from an early-exit path).
 *   - Tokens expire 72 hours after issuance.
 *   - On cancellation, token is nulled immediately (see WPAPPT_Model_Booking::update_status).
 *   - On each reschedule, consume() replaces the old token so any forwarded
 *     or cached link is invalidated immediately.
 */
class WPAPPT_Service_Token {

	/** Token lifetime in seconds (72 hours). */
	const EXPIRY_SECONDS = 72 * HOUR_IN_SECONDS;

	private WPAPPT_Model_Booking $booking_model;

	public function __construct( ?WPAPPT_Model_Booking $booking_model = null ) {
		$this->booking_model = $booking_model ?? new WPAPPT_Model_Booking();
	}

	// =========================================================================
	// Hook registration
	// =========================================================================

	/**
	 * Register action hooks.
	 *
	 * Runs at priority 5 — before the email service (priority 10) — so the
	 * token is stored and the wpappt_booking_confirmed action is fired before
	 * the email service tries to send the confirmation message.
	 */
	public function init(): void {
		add_action( 'wpappt_booking_status_changed', [ $this, 'on_status_changed' ], 5, 4 );
	}

	// =========================================================================
	// Hook callbacks
	// =========================================================================

	/**
	 * Generate a token when a booking is confirmed, then fire
	 * wpappt_booking_confirmed so the email service can send the confirmation
	 * with the fresh reschedule link embedded.
	 */
	public function on_status_changed( int $booking_id, string $new_status, string $actor = '', array $attachments = [] ): void {
		if ( 'confirmed' !== $new_status ) {
			return;
		}

		$raw_token = $this->generate( $booking_id );
		$link      = $this->build_link( $raw_token );

		do_action( 'wpappt_booking_confirmed', $booking_id, $link, $attachments );
	}

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Generate a new reschedule token for a booking.
	 *
	 * Stores the SHA-256 hash and expiry datetime in the database.
	 * Returns the raw token so the caller can embed it in an email link.
	 */
	public function generate( int $booking_id ): string {
		$raw_token  = bin2hex( random_bytes( 32 ) );
		$token_hash = hash( 'sha256', $raw_token );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::EXPIRY_SECONDS );

		$this->booking_model->set_reschedule_token( $booking_id, $token_hash, $expires_at );

		return $raw_token;
	}

	/**
	 * Validate a raw reschedule token from an incoming request.
	 *
	 * The DB query (find_by_token_hash) already guards IS NOT NULL and expiry,
	 * so null is returned for expired, nulled, or unknown tokens. hash_equals()
	 * provides constant-time comparison as a defence-in-depth measure.
	 *
	 * @return array<string, mixed>|null  Booking row, or null if the token is invalid.
	 */
	public function validate( string $raw_token ): ?array {
		if ( '' === $raw_token ) {
			return null;
		}

		$token_hash = hash( 'sha256', $raw_token );
		$booking    = $this->booking_model->find_by_token_hash( $token_hash );

		if ( null === $booking ) {
			return null;
		}

		// hash_equals prevents timing attacks should a caching layer bypass
		// the DB's constant-time string comparison.
		if ( ! hash_equals( (string) $booking['reschedule_token'], $token_hash ) ) {
			return null;
		}

		return $booking;
	}

	/**
	 * Invalidate the current token by overwriting it with a new one.
	 *
	 * Called immediately after a successful reschedule so the link the
	 * customer just used cannot be replayed.
	 *
	 * Returns the raw replacement token so the caller can build a new link
	 * for the confirmation email.
	 */
	public function consume( int $booking_id ): string {
		return $this->generate( $booking_id );
	}

	/**
	 * Build the full reschedule URL for a raw token.
	 *
	 * Uses the admin-configured booking page (wpappt_booking_page option) as
	 * the base, falling back to the site home URL.
	 */
	public function build_link( string $raw_token ): string {
		$page_id = (int) get_option( 'wpappt_booking_page', 0 );
		$base    = $page_id > 0
			? (string) get_permalink( $page_id )
			: (string) home_url();

		return add_query_arg( 'reschedule', $raw_token, $base );
	}
}
