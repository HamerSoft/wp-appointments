<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Services management view.
 *
 * Expected variables:
 *   @var array<int, array<string, mixed>>  $services     All services (active + inactive).
 *   @var array<string, mixed>|null         $edit_service Service being edited, or null for add form.
 */

$is_editing   = null !== $edit_service;
$form_heading = $is_editing
	? __( 'Edit Service', 'wp-appointments' )
	: __( 'Add New Service', 'wp-appointments' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Services', 'wp-appointments' ); ?></h1>
	<hr class="wp-header-end">

	<div class="wpappt-two-col">

		<!-- ---------------------------------------------------------------- -->
		<!-- Services list                                                     -->
		<!-- ---------------------------------------------------------------- -->
		<div>
			<h2><?php esc_html_e( 'All Services', 'wp-appointments' ); ?></h2>
			<?php if ( empty( $services ) ) : ?>
				<p><?php esc_html_e( 'No services yet. Add your first service using the form.', 'wp-appointments' ); ?></p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'wp-appointments' ); ?></th>
						<th><?php esc_html_e( 'Duration', 'wp-appointments' ); ?></th>
						<th><?php esc_html_e( 'Price', 'wp-appointments' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-appointments' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wp-appointments' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $services as $service ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $service['name'] ); ?></strong></td>
						<td>
							<?php
							printf(
								/* translators: %d: minutes */
								esc_html__( '%d min', 'wp-appointments' ),
								(int) $service['duration_mins']
							);
							?>
						</td>
						<td>
							<?php
							printf(
								'&euro;%s',
								esc_html( number_format( (float) $service['price'], 2 ) )
							);
							?>
						</td>
						<td>
							<?php if ( $service['is_active'] ) : ?>
								<span class="wpappt-badge wpappt-badge--confirmed"><?php esc_html_e( 'Active', 'wp-appointments' ); ?></span>
							<?php else : ?>
								<span class="wpappt-badge wpappt-badge--cancelled"><?php esc_html_e( 'Inactive', 'wp-appointments' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpappt-services', 'action' => 'edit', 'id' => $service['id'] ], admin_url( 'admin.php' ) ) ); ?>">
								<?php esc_html_e( 'Edit', 'wp-appointments' ); ?>
							</a>
							&nbsp;|&nbsp;
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
								  style="display:inline"
								  data-wpappt-confirm="<?php esc_attr_e( 'Delete this service?', 'wp-appointments' ); ?>">
								<input type="hidden" name="action"     value="wpappt_delete_service">
								<input type="hidden" name="service_id" value="<?php echo (int) $service['id']; ?>">
								<?php wp_nonce_field( "wpappt_delete_service_{$service['id']}" ); ?>
								<button type="submit" class="button-link wpappt-link-danger">
									<?php esc_html_e( 'Delete', 'wp-appointments' ); ?>
								</button>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>

		<!-- ---------------------------------------------------------------- -->
		<!-- Add / Edit form                                                   -->
		<!-- ---------------------------------------------------------------- -->
		<div>
			<h2><?php echo esc_html( $form_heading ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpappt_save_service">
				<?php if ( $is_editing ) : ?>
					<input type="hidden" name="service_id" value="<?php echo (int) $edit_service['id']; ?>">
				<?php endif; ?>
				<?php wp_nonce_field( 'wpappt_save_service' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="wpappt-svc-name"><?php esc_html_e( 'Name', 'wp-appointments' ); ?></label></th>
						<td>
							<input type="text" id="wpappt-svc-name" name="name"
								   value="<?php echo $is_editing ? esc_attr( $edit_service['name'] ) : ''; ?>"
								   class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th><label for="wpappt-svc-duration"><?php esc_html_e( 'Duration (minutes)', 'wp-appointments' ); ?></label></th>
						<td>
							<input type="number" id="wpappt-svc-duration" name="duration_mins"
								   value="<?php echo $is_editing ? (int) $edit_service['duration_mins'] : 60; ?>"
								   min="1" step="1" class="small-text" required>
						</td>
					</tr>
					<tr>
						<th><label for="wpappt-svc-price"><?php esc_html_e( 'Price (&euro;)', 'wp-appointments' ); ?></label></th>
						<td>
							<input type="number" id="wpappt-svc-price" name="price"
								   value="<?php echo $is_editing ? esc_attr( number_format( (float) $edit_service['price'], 2 ) ) : '0.00'; ?>"
								   min="0" step="0.01" class="small-text">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Active', 'wp-appointments' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="is_active" value="1"
									   <?php checked( $is_editing ? $edit_service['is_active'] : 1, 1 ); ?>>
								<?php esc_html_e( 'Show this service in the booking widget', 'wp-appointments' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="wpappt-svc-order"><?php esc_html_e( 'Sort Order', 'wp-appointments' ); ?></label></th>
						<td>
							<input type="number" id="wpappt-svc-order" name="sort_order"
								   value="<?php echo $is_editing ? (int) $edit_service['sort_order'] : 0; ?>"
								   step="1" class="small-text">
							<p class="description"><?php esc_html_e( 'Lower numbers appear first.', 'wp-appointments' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( $is_editing ? __( 'Update Service', 'wp-appointments' ) : __( 'Add Service', 'wp-appointments' ) ); ?>

				<?php if ( $is_editing ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpappt-services' ) ); ?>">
					<?php esc_html_e( 'Cancel', 'wp-appointments' ); ?>
				</a>
				<?php endif; ?>
			</form>
		</div>

	</div><!-- .wpappt-two-col -->
</div><!-- .wrap -->
