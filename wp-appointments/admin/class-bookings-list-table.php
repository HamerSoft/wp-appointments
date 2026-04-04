<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Bookings list table — extends WP_List_Table.
 *
 * Sortable columns, status-filter tabs, bulk Confirm/Cancel, and search.
 */
class WPAPPT_Admin_Bookings_List_Table extends WP_List_Table {

	private WPAPPT_Model_Booking $booking_model;
	private WPAPPT_Model_Service $service_model;

	/** Service rows keyed by ID for fast lookup during column rendering. */
	private array $services_map = [];

	public function __construct( WPAPPT_Model_Booking $booking_model, WPAPPT_Model_Service $service_model ) {
		parent::__construct( [
			'singular' => 'booking',
			'plural'   => 'bookings',
			'ajax'     => false,
		] );

		$this->booking_model = $booking_model;
		$this->service_model = $service_model;

		foreach ( $service_model->find_all() as $service ) {
			$this->services_map[ (int) $service['id'] ] = $service;
		}
	}

	// -------------------------------------------------------------------------
	// Schema
	// -------------------------------------------------------------------------

	public function get_columns(): array {
		return [
			'cb'               => '<input type="checkbox" />',
			'id'               => __( '#', 'wp-appointments' ),
			'customer_name'    => __( 'Customer', 'wp-appointments' ),
			'service'          => __( 'Service', 'wp-appointments' ),
			'appointment_date' => __( 'Appointment', 'wp-appointments' ),
			'status'           => __( 'Status', 'wp-appointments' ),
			'created_at'       => __( 'Submitted', 'wp-appointments' ),
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'id'               => [ 'id',               false ],
			'customer_name'    => [ 'customer_name',    false ],
			'appointment_date' => [ 'appointment_date', true  ], // default sort
			'status'           => [ 'status',           false ],
			'created_at'       => [ 'created_at',       false ],
		];
	}

	protected function get_bulk_actions(): array {
		return [
			'confirm' => __( 'Confirm', 'wp-appointments' ),
			'cancel'  => __( 'Cancel',  'wp-appointments' ),
		];
	}

	// -------------------------------------------------------------------------
	// Bulk action processing
	// -------------------------------------------------------------------------

	public function process_bulk_action(): void {
		$action = $this->current_action();

		if ( ! $action || ! in_array( $action, [ 'confirm', 'cancel' ], true ) ) {
			return;
		}

		check_admin_referer( 'bulk-bookings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-appointments' ) );
		}

		$ids = array_map( 'intval', (array) ( $_POST['booking_ids'] ?? [] ) );

		if ( empty( $ids ) ) {
			return;
		}

		$new_status = ( 'confirm' === $action ) ? 'confirmed' : 'cancelled';
		$actor      = wp_get_current_user()->user_login ?: 'admin';

		foreach ( $ids as $id ) {
			$this->booking_model->update_status( $id, $new_status, $actor );
		}

		// Fire hook so the email service (Step 6) can send notifications.
		foreach ( $ids as $id ) {
			do_action( 'wpappt_booking_status_changed', $id, $new_status, 'bulk' );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'          => 'wpappt-bookings',
					'wpappt_notice' => 'booking_' . ( 'confirm' === $action ? 'confirmed' : 'cancelled' ),
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Data
	// -------------------------------------------------------------------------

	public function prepare_items(): void {
		$per_page = $this->get_items_per_page( 'wpappt_bookings_per_page', 20 );
		$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification

		$args = [
			'status'   => sanitize_key( $_GET['status'] ?? '' ),   // phpcs:ignore WordPress.Security.NonceVerification
			'search'   => sanitize_text_field( $_GET['s']      ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification
			'orderby'  => sanitize_key( $_GET['orderby']  ?? 'appointment_date' ), // phpcs:ignore WordPress.Security.NonceVerification
			'order'    => sanitize_key( $_GET['order']    ?? 'DESC' ),  // phpcs:ignore WordPress.Security.NonceVerification
			'per_page' => $per_page,
			'page'     => $paged,
		];

		$this->items = $this->booking_model->find_all( $args );
		$total       = $this->booking_model->count_all( $args );

		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		] );

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];
	}

	// -------------------------------------------------------------------------
	// Status filter tabs
	// -------------------------------------------------------------------------

	protected function get_views(): array {
		$current = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$base    = admin_url( 'admin.php?page=wpappt-bookings' );

		$counts = [
			''          => $this->booking_model->count_all(),
			'pending'   => $this->booking_model->count_all( [ 'status' => 'pending'   ] ),
			'confirmed' => $this->booking_model->count_all( [ 'status' => 'confirmed' ] ),
			'cancelled' => $this->booking_model->count_all( [ 'status' => 'cancelled' ] ),
		];

		$labels = [
			''          => __( 'All',       'wp-appointments' ),
			'pending'   => __( 'Pending',   'wp-appointments' ),
			'confirmed' => __( 'Confirmed', 'wp-appointments' ),
			'cancelled' => __( 'Cancelled', 'wp-appointments' ),
		];

		$views = [];
		foreach ( $labels as $status => $label ) {
			$url     = $status ? add_query_arg( 'status', $status, $base ) : $base;
			$active  = ( $current === $status ) ? ' class="current"' : '';
			$views[ $status ?: 'all' ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $url ),
				$active,
				esc_html( $label ),
				(int) $counts[ $status ]
			);
		}

		return $views;
	}

	// -------------------------------------------------------------------------
	// Column renderers
	// -------------------------------------------------------------------------

	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="booking_ids[]" value="%d" />',
			(int) $item['id']
		);
	}

	protected function column_default( $item, $column_name ): string {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	protected function column_id( array $item ): string {
		$view_url = add_query_arg(
			[ 'page' => 'wpappt-bookings', 'action' => 'view', 'id' => $item['id'] ],
			admin_url( 'admin.php' )
		);

		return sprintf(
			'<a href="%s"><strong>#%d</strong></a>',
			esc_url( $view_url ),
			(int) $item['id']
		);
	}

	protected function column_customer_name( array $item ): string {
		$view_url = add_query_arg(
			[ 'page' => 'wpappt-bookings', 'action' => 'view', 'id' => $item['id'] ],
			admin_url( 'admin.php' )
		);

		return sprintf(
			'<a href="%s">%s</a><br><span class="description">%s</span>',
			esc_url( $view_url ),
			esc_html( $item['customer_name'] ),
			esc_html( $item['customer_email'] )
		);
	}

	protected function column_service( array $item ): string {
		$service_id = (int) $item['service_id'];
		return isset( $this->services_map[ $service_id ] )
			? esc_html( $this->services_map[ $service_id ]['name'] )
			: esc_html__( '(deleted)', 'wp-appointments' );
	}

	protected function column_appointment_date( array $item ): string {
		return sprintf(
			'%s<br><span class="description">%s – %s</span>',
			esc_html( $item['appointment_date'] ),
			esc_html( substr( $item['start_time'], 0, 5 ) ),
			esc_html( substr( $item['end_time'],   0, 5 ) )
		);
	}

	protected function column_status( array $item ): string {
		$status = esc_attr( $item['status'] );
		return sprintf(
			'<span class="wpappt-badge wpappt-badge--%s">%s</span>',
			$status,
			esc_html( ucfirst( $item['status'] ) )
		);
	}

	protected function column_created_at( array $item ): string {
		return esc_html( $item['created_at'] );
	}
}
