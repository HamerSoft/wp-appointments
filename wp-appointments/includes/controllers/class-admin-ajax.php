<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all admin-post.php form submissions for the plugin.
 *
 * Every handler:
 *   1. Verifies the action-specific nonce.
 *   2. Checks manage_options capability.
 *   3. Sanitizes input via WPAPPT_Helper_Sanitizer.
 *   4. Calls the relevant model method.
 *   5. Redirects with a notice code.
 */
class WPAPPT_Controller_Admin_Ajax {

	private WPAPPT_Model_Booking      $booking_model;
	private WPAPPT_Model_Service      $service_model;
	private WPAPPT_Model_Availability $availability_model;

	public function __construct() {
		$this->booking_model      = new WPAPPT_Model_Booking();
		$this->service_model      = new WPAPPT_Model_Service();
		$this->availability_model = new WPAPPT_Model_Availability();
	}

	public function init(): void {
		$actions = [
			'wpappt_confirm_booking',
			'wpappt_cancel_booking',
			'wpappt_save_booking_notes',
			'wpappt_send_followup',
			'wpappt_save_service',
			'wpappt_delete_service',
			'wpappt_save_availability',
			'wpappt_add_blocked_slot',
			'wpappt_delete_blocked_slot',
		];

		foreach ( $actions as $action ) {
			// Strip 'wpappt_' prefix to get method name: 'confirm_booking', etc.
			$method = substr( $action, strlen( 'wpappt_' ) );
			add_action( "admin_post_{$action}", [ $this, "handle_{$method}" ] );
		}
	}

	// =========================================================================
	// Booking actions
	// =========================================================================

	public function handle_confirm_booking(): void {
		$id = (int) ( $_POST['booking_id'] ?? 0 );
		check_admin_referer( "wpappt_confirm_booking_{$id}" );
		$this->require_capability();

		$actor   = wp_get_current_user()->user_login ?: 'admin';
		$success = $this->booking_model->update_status( $id, 'confirmed', $actor );

		if ( ! $success ) {
			$this->redirect( 'wpappt-bookings', 'error_not_found', [ 'action' => 'view', 'id' => $id ] );
		}

		do_action( 'wpappt_booking_status_changed', $id, 'confirmed', 'admin' );

		$this->redirect( 'wpappt-bookings', 'booking_confirmed', [ 'action' => 'view', 'id' => $id ] );
	}

	public function handle_cancel_booking(): void {
		$id = (int) ( $_POST['booking_id'] ?? 0 );
		check_admin_referer( "wpappt_cancel_booking_{$id}" );
		$this->require_capability();

		$actor   = wp_get_current_user()->user_login ?: 'admin';
		$success = $this->booking_model->update_status( $id, 'cancelled', $actor );

		if ( ! $success ) {
			$this->redirect( 'wpappt-bookings', 'error_not_found', [ 'action' => 'view', 'id' => $id ] );
		}

		do_action( 'wpappt_booking_status_changed', $id, 'cancelled', 'admin' );

		$this->redirect( 'wpappt-bookings', 'booking_cancelled', [ 'action' => 'view', 'id' => $id ] );
	}

	public function handle_save_booking_notes(): void {
		$id = (int) ( $_POST['booking_id'] ?? 0 );
		check_admin_referer( "wpappt_save_notes_{$id}" );
		$this->require_capability();

		$notes   = WPAPPT_Helper_Sanitizer::admin_notes( $_POST['admin_notes'] ?? '' );
		$success = $this->booking_model->update( $id, [ 'admin_notes' => $notes ] );

		$notice = $success ? 'booking_notes_saved' : 'error_save';
		$this->redirect( 'wpappt-bookings', $notice, [ 'action' => 'view', 'id' => $id ] );
	}

	public function handle_send_followup(): void {
		$id = (int) ( $_POST['booking_id'] ?? 0 );
		check_admin_referer( "wpappt_send_followup_{$id}" );
		$this->require_capability();

		$message = WPAPPT_Helper_Sanitizer::followup_message( $_POST['followup_message'] ?? '' );

		if ( '' === $message ) {
			$this->redirect( 'wpappt-bookings', 'error_invalid', [ 'action' => 'view', 'id' => $id ] );
		}

		// Email service (Step 6) hooks into this action.
		do_action( 'wpappt_send_followup_email', $id, $message );

		$this->redirect( 'wpappt-bookings', 'followup_sent', [ 'action' => 'view', 'id' => $id ] );
	}

	// =========================================================================
	// Service actions
	// =========================================================================

	public function handle_save_service(): void {
		check_admin_referer( 'wpappt_save_service' );
		$this->require_capability();

		$data = WPAPPT_Helper_Sanitizer::service_input( $_POST );

		if ( '' === $data['name'] ) {
			$this->redirect( 'wpappt-services', 'error_invalid' );
		}

		$id = (int) ( $_POST['service_id'] ?? 0 );

		if ( $id > 0 ) {
			$success = $this->service_model->update( $id, $data );
		} else {
			$success = false !== $this->service_model->create( $data );
		}

		$notice = $success ? 'service_saved' : 'error_save';
		$this->redirect( 'wpappt-services', $notice );
	}

	public function handle_delete_service(): void {
		$id = (int) ( $_POST['service_id'] ?? 0 );
		check_admin_referer( "wpappt_delete_service_{$id}" );
		$this->require_capability();

		$success = $this->service_model->delete( $id );
		$notice  = $success ? 'service_deleted' : 'error_not_found';
		$this->redirect( 'wpappt-services', $notice );
	}

	// =========================================================================
	// Availability actions
	// =========================================================================

	public function handle_save_availability(): void {
		check_admin_referer( 'wpappt_save_availability' );
		$this->require_capability();

		// Expect $_POST['availability'] = [ day_of_week => [ start_time, end_time, is_available ] ]
		$posted = isset( $_POST['availability'] ) && is_array( $_POST['availability'] )
			? $_POST['availability']
			: [];

		for ( $day = 0; $day <= 6; $day++ ) {
			$raw = $posted[ $day ] ?? [];

			// An unchecked day results in an empty slot list (marks it unavailable).
			if ( empty( $raw['is_available'] ) ) {
				$this->availability_model->save_day( $day, [] );
				continue;
			}

			$slot = WPAPPT_Helper_Sanitizer::availability_slot(
				array_merge( $raw, [ 'day_of_week' => $day ] )
			);

			// Skip if times are empty or invalid order.
			if ( '' === $slot['start_time'] || '' === $slot['end_time']
				|| $slot['start_time'] >= $slot['end_time'] ) {
				$this->availability_model->save_day( $day, [] );
				continue;
			}

			$this->availability_model->save_day( $day, [ $slot ] );
		}

		$this->redirect( 'wpappt-availability', 'availability_saved' );
	}

	public function handle_add_blocked_slot(): void {
		check_admin_referer( 'wpappt_add_blocked_slot' );
		$this->require_capability();

		$data = WPAPPT_Helper_Sanitizer::blocked_slot_input( $_POST );

		// Basic validation: date must be parseable and times must be ordered.
		$date_ok  = (bool) \DateTime::createFromFormat( 'Y-m-d', $data['blocked_date'] );
		$times_ok = '' !== $data['start_time']
			&& '' !== $data['end_time']
			&& $data['start_time'] < $data['end_time'];

		if ( ! $date_ok || ! $times_ok ) {
			$this->redirect( 'wpappt-availability', 'error_invalid' );
		}

		$result = $this->availability_model->add_blocked_slot( $data );
		$notice = ( false !== $result ) ? 'blocked_slot_added' : 'error_save';
		$this->redirect( 'wpappt-availability', $notice );
	}

	public function handle_delete_blocked_slot(): void {
		$id = (int) ( $_POST['blocked_slot_id'] ?? 0 );
		check_admin_referer( "wpappt_delete_blocked_slot_{$id}" );
		$this->require_capability();

		$success = $this->availability_model->delete_blocked_slot( $id );
		$notice  = $success ? 'blocked_slot_deleted' : 'error_not_found';
		$this->redirect( 'wpappt-availability', $notice );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	private function require_capability(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-appointments' ) );
		}
	}

	/**
	 * Redirect to an admin page with a notice code.
	 *
	 * @param array<string, mixed> $extra Additional query args (e.g. action, id).
	 */
	private function redirect( string $page, string $notice, array $extra = [] ): never {
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					[ 'page' => $page, 'wpappt_notice' => $notice ],
					$extra
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
