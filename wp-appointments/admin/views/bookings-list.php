<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bookings list view.
 *
 * Expected variables (set by WPAPPT_Admin::render_bookings_page):
 *   @var WPAPPT_Admin_Bookings_List_Table $list_table
 */
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Bookings', 'wp-appointments' ); ?></h1>
	<hr class="wp-header-end">

	<form method="get">
		<input type="hidden" name="page" value="wpappt-bookings">
		<?php
		$list_table->search_box( __( 'Search bookings', 'wp-appointments' ), 'wpappt-search' );
		$list_table->views();
		$list_table->display();
		?>
	</form>
</div>
