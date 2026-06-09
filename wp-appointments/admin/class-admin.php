<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin menus, enqueues assets, routes page requests to views,
 * and displays transient admin notices.
 */
class WPAPPT_Admin {

	private WPAPPT_Model_Booking      $booking_model;
	private WPAPPT_Model_Service      $service_model;
	private WPAPPT_Model_Availability $availability_model;
	private WPAPPT_Admin_Settings_Page $settings_page;

	public function __construct() {
		$this->booking_model      = new WPAPPT_Model_Booking();
		$this->service_model      = new WPAPPT_Model_Service();
		$this->availability_model = new WPAPPT_Model_Availability();
		$this->settings_page      = new WPAPPT_Admin_Settings_Page();
	}

	public function init(): void {
		add_action( 'admin_menu',            [ $this, 'register_menus'        ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets'        ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_menu_icon_fix' ] );
		add_action( 'admin_notices',         [ $this, 'display_notices'       ] );
	}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	public function register_menus(): void {
		add_menu_page(
			__( 'Appointments', 'wp-appointments' ),
			__( 'Appointments', 'wp-appointments' ),
			'manage_options',
			'wpappt-bookings',
			[ $this, 'render_bookings_page' ],
			WPAPPT_PLUGIN_URL . 'assets/images/wp-logo.png',
			30
		);

		// First sub-page repeats the parent slug so WP hides the duplicate.
		add_submenu_page(
			'wpappt-bookings',
			__( 'Bookings', 'wp-appointments' ),
			__( 'Bookings', 'wp-appointments' ),
			'manage_options',
			'wpappt-bookings',
			[ $this, 'render_bookings_page' ]
		);

		add_submenu_page(
			'wpappt-bookings',
			__( 'Services', 'wp-appointments' ),
			__( 'Services', 'wp-appointments' ),
			'manage_options',
			'wpappt-services',
			[ $this, 'render_services_page' ]
		);

		add_submenu_page(
			'wpappt-bookings',
			__( 'Availability', 'wp-appointments' ),
			__( 'Availability', 'wp-appointments' ),
			'manage_options',
			'wpappt-availability',
			[ $this, 'render_availability_page' ]
		);

		add_submenu_page(
			'wpappt-bookings',
			__( 'Settings', 'wp-appointments' ),
			__( 'Settings', 'wp-appointments' ),
			'manage_options',
			'wpappt-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_menu_icon_fix(): void {
		wp_add_inline_style(
			'wp-admin',
			'#adminmenu #toplevel_page_wpappt-bookings .wp-menu-image img { width: 100%; height: 100%; padding: 0; filter: brightness(0) invert(1); opacity: 0.6; }
#adminmenu #toplevel_page_wpappt-bookings:hover .wp-menu-image img,
#adminmenu #toplevel_page_wpappt-bookings.wp-has-current-submenu .wp-menu-image img,
#adminmenu #toplevel_page_wpappt-bookings.current .wp-menu-image img { opacity: 0.8; }'
		);
	}

	public function enqueue_assets( string $hook ): void {
		$plugin_hooks = [
			'toplevel_page_wpappt-bookings',
			'appointments_page_wpappt-services',
			'appointments_page_wpappt-availability',
			'appointments_page_wpappt-settings',
		];

		if ( ! in_array( $hook, $plugin_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wpappt-admin',
			WPAPPT_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WPAPPT_VERSION
		);

		wp_enqueue_script(
			'wpappt-admin',
			WPAPPT_PLUGIN_URL . 'assets/js/admin.js',
			[],
			WPAPPT_VERSION,
			true
		);

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( 'toplevel_page_wpappt-bookings' === $hook && 'view' === ( $_GET['action'] ?? '' ) ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'wpappt-booking-attachments',
				WPAPPT_PLUGIN_URL . 'assets/js/admin-booking-attachments.js',
				[],
				WPAPPT_VERSION,
				true
			);
		}
	}

	// -------------------------------------------------------------------------
	// Admin notices
	// -------------------------------------------------------------------------

	public function display_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'wpappt' ) === false ) {
			return;
		}

		$notice = sanitize_key( $_GET['wpappt_notice'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $notice ) {
			return;
		}

		$messages = [
			'booking_confirmed'    => [ 'success', __( 'Booking confirmed.',                  'wp-appointments' ) ],
			'booking_cancelled'    => [ 'success', __( 'Booking cancelled.',                  'wp-appointments' ) ],
			'booking_notes_saved'  => [ 'success', __( 'Notes saved.',                        'wp-appointments' ) ],
			'followup_sent'        => [ 'success', __( 'Follow-up email sent.',               'wp-appointments' ) ],
			'service_saved'        => [ 'success', __( 'Service saved.',                      'wp-appointments' ) ],
			'service_deleted'      => [ 'success', __( 'Service deleted.',                    'wp-appointments' ) ],
			'availability_saved'   => [ 'success', __( 'Availability saved.',                 'wp-appointments' ) ],
			'blocked_slot_added'   => [ 'success', __( 'Blocked slot added.',                 'wp-appointments' ) ],
			'blocked_slot_deleted' => [ 'success', __( 'Blocked slot removed.',               'wp-appointments' ) ],
			'blocked_series_deleted' => [ 'success', __( 'Blocked series removed.',             'wp-appointments' ) ],
			'error_nonce'          => [ 'error',   __( 'Security check failed. Please try again.', 'wp-appointments' ) ],
			'error_not_found'      => [ 'error',   __( 'Record not found.',                   'wp-appointments' ) ],
			'error_invalid'        => [ 'error',   __( 'Invalid request.',                    'wp-appointments' ) ],
			'error_save'           => [ 'error',   __( 'Could not save. Please try again.',   'wp-appointments' ) ],
		];

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		[ $type, $message ] = $messages[ $notice ];

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	public function render_bookings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-appointments' ) );
		}

		$action = sanitize_key( $_GET['action'] ?? 'list' ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'view' === $action && ! empty( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$booking_id = (int) $_GET['id']; // phpcs:ignore WordPress.Security.NonceVerification
			$booking    = $this->booking_model->find( $booking_id );

			if ( ! $booking ) {
				wp_die( esc_html__( 'Booking not found.', 'wp-appointments' ) );
			}

			$service = $this->service_model->find( (int) $booking['service_id'] );
			include WPAPPT_PLUGIN_DIR . 'admin/views/booking-detail.php';
			return;
		}

		$list_table = new WPAPPT_Admin_Bookings_List_Table( $this->booking_model, $this->service_model );
		$list_table->process_bulk_action();
		$list_table->prepare_items();
		include WPAPPT_PLUGIN_DIR . 'admin/views/bookings-list.php';
	}

	public function render_services_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-appointments' ) );
		}

		$services     = $this->service_model->find_all();
		$edit_service = null;

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( 'edit' === sanitize_key( $_GET['action'] ?? '' ) && ! empty( $_GET['id'] ) ) {
			$edit_service = $this->service_model->find( (int) $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		include WPAPPT_PLUGIN_DIR . 'admin/views/services-page.php';
	}

	public function render_availability_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-appointments' ) );
		}

		$availability  = $this->availability_model->find_all();
		$blocked_slots = $this->availability_model->find_upcoming_blocked();
		include WPAPPT_PLUGIN_DIR . 'admin/views/availability-page.php';
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-appointments' ) );
		}

		$this->settings_page->render();
	}
}
