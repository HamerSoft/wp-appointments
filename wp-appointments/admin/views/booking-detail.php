<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking detail view.
 *
 * Expected variables (set by WPAPPT_Admin::render_bookings_page):
 *   @var array<string, mixed>      $booking
 *   @var array<string, mixed>|null $service
 */

$back_url      = admin_url( 'admin.php?page=wpappt-bookings' );
$booking_id    = (int) $booking['id'];
$status        = $booking['status'];
$can_confirm   = ( 'pending'   === $status );
$can_cancel    = ( 'cancelled' !== $status );
?>
<div class="wrap">
	<h1>
		<?php
		printf(
			/* translators: %d: booking ID */
			esc_html__( 'Booking #%d', 'wp-appointments' ),
			$booking_id
		);
		?>
		<span class="wpappt-badge wpappt-badge--<?php echo esc_attr( $status ); ?>">
			<?php echo esc_html( ucfirst( $status ) ); ?>
		</span>
	</h1>

	<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
		&larr; <?php esc_html_e( 'Back to Bookings', 'wp-appointments' ); ?>
	</a>
	<hr class="wp-header-end">

	<div class="wpappt-detail-grid">

		<!-- ---------------------------------------------------------------- -->
		<!-- Booking details                                                   -->
		<!-- ---------------------------------------------------------------- -->
		<div class="wpappt-detail-section">
			<h2><?php esc_html_e( 'Appointment', 'wp-appointments' ); ?></h2>
			<table class="form-table widefat">
				<tr>
					<th><?php esc_html_e( 'Service', 'wp-appointments' ); ?></th>
					<td><?php echo $service ? esc_html( $service['name'] ) : esc_html__( '(deleted)', 'wp-appointments' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Date', 'wp-appointments' ); ?></th>
					<td><?php echo esc_html( $booking['appointment_date'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Time', 'wp-appointments' ); ?></th>
					<td>
						<?php
						echo esc_html( substr( $booking['start_time'], 0, 5 ) )
							. ' – '
							. esc_html( substr( $booking['end_time'], 0, 5 ) );
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Submitted', 'wp-appointments' ); ?></th>
					<td><?php echo esc_html( $booking['created_at'] ); ?></td>
				</tr>
				<?php if ( ! empty( $booking['updated_at'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Last updated', 'wp-appointments' ); ?></th>
					<td><?php echo esc_html( $booking['updated_at'] ); ?></td>
				</tr>
				<?php endif; ?>
			</table>
		</div>

		<!-- ---------------------------------------------------------------- -->
		<!-- Customer details                                                  -->
		<!-- ---------------------------------------------------------------- -->
		<div class="wpappt-detail-section">
			<h2><?php esc_html_e( 'Customer', 'wp-appointments' ); ?></h2>
			<table class="form-table widefat">
				<tr>
					<th><?php esc_html_e( 'Name', 'wp-appointments' ); ?></th>
					<td><?php echo esc_html( $booking['customer_name'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Email', 'wp-appointments' ); ?></th>
					<td>
						<a href="mailto:<?php echo esc_attr( $booking['customer_email'] ); ?>">
							<?php echo esc_html( $booking['customer_email'] ); ?>
						</a>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Phone', 'wp-appointments' ); ?></th>
					<td><?php echo esc_html( $booking['customer_phone'] ); ?></td>
				</tr>
				<?php if ( ! empty( $booking['injury_notes'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Injury notes', 'wp-appointments' ); ?></th>
					<td><?php echo nl2br( esc_html( $booking['injury_notes'] ) ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( ! empty( $booking['comments'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Comments', 'wp-appointments' ); ?></th>
					<td><?php echo nl2br( esc_html( $booking['comments'] ) ); ?></td>
				</tr>
				<?php endif; ?>
			</table>
		</div>

		<!-- ---------------------------------------------------------------- -->
		<!-- Status actions                                                    -->
		<!-- ---------------------------------------------------------------- -->
		<div class="wpappt-detail-section">
			<h2><?php esc_html_e( 'Actions', 'wp-appointments' ); ?></h2>

			<?php if ( $can_confirm ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action"     value="wpappt_confirm_booking">
				<input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
				<?php wp_nonce_field( "wpappt_confirm_booking_{$booking_id}" ); ?>
				<p class="wpappt-attachment-picker">
					<button type="button" class="button wpappt-attach-btn">
						<?php esc_html_e( 'Attach file', 'wp-appointments' ); ?>
					</button>
					<span class="wpappt-attachment-name" style="display:none;"></span>
					<button type="button" class="wpappt-attachment-clear" style="display:none;"
							aria-label="<?php esc_attr_e( 'Remove attachment', 'wp-appointments' ); ?>">&#x2715;</button>
					<input type="hidden" name="attachment_id" class="wpappt-attachment-id" value="">
				</p>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Confirm Booking', 'wp-appointments' ); ?>
				</button>
			</form>
			<?php endif; ?>

			<?php if ( $can_cancel ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				  style="margin-top: 8px;"
				  data-wpappt-confirm="<?php esc_attr_e( 'Cancel this booking?', 'wp-appointments' ); ?>">
				<input type="hidden" name="action"     value="wpappt_cancel_booking">
				<input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
				<?php wp_nonce_field( "wpappt_cancel_booking_{$booking_id}" ); ?>
				<p class="wpappt-attachment-picker">
					<button type="button" class="button wpappt-attach-btn">
						<?php esc_html_e( 'Attach file', 'wp-appointments' ); ?>
					</button>
					<span class="wpappt-attachment-name" style="display:none;"></span>
					<button type="button" class="wpappt-attachment-clear" style="display:none;"
							aria-label="<?php esc_attr_e( 'Remove attachment', 'wp-appointments' ); ?>">&#x2715;</button>
					<input type="hidden" name="attachment_id" class="wpappt-attachment-id" value="">
				</p>
				<button type="submit" class="button button-secondary wpappt-btn-danger">
					<?php esc_html_e( 'Cancel Booking', 'wp-appointments' ); ?>
				</button>
			</form>
			<?php endif; ?>
		</div>

		<!-- ---------------------------------------------------------------- -->
		<!-- Admin notes                                                       -->
		<!-- ---------------------------------------------------------------- -->
		<div class="wpappt-detail-section">
			<h2><?php esc_html_e( 'Admin Notes', 'wp-appointments' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action"     value="wpappt_save_booking_notes">
				<input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
				<?php wp_nonce_field( "wpappt_save_notes_{$booking_id}" ); ?>
				<textarea
					name="admin_notes"
					rows="5"
					class="large-text"
				><?php echo esc_textarea( $booking['admin_notes'] ?? '' ); ?></textarea>
				<p>
					<button type="submit" class="button">
						<?php esc_html_e( 'Save Notes', 'wp-appointments' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- ---------------------------------------------------------------- -->
		<!-- Follow-up email                                                   -->
		<!-- ---------------------------------------------------------------- -->
		<div class="wpappt-detail-section">
			<h2><?php esc_html_e( 'Send Follow-up Email', 'wp-appointments' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action"     value="wpappt_send_followup">
				<input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
				<?php wp_nonce_field( "wpappt_send_followup_{$booking_id}" ); ?>
				<p>
					<label for="wpappt-followup-msg">
						<?php
						printf(
							/* translators: %s: customer name */
							esc_html__( 'Message to %s:', 'wp-appointments' ),
							esc_html( $booking['customer_name'] )
						);
						?>
					</label>
				</p>
				<textarea
					id="wpappt-followup-msg"
					name="followup_message"
					rows="6"
					class="large-text"
					maxlength="2000"
				></textarea>
				<p class="wpappt-char-count description">
					<span id="wpappt-followup-remaining">2000</span>
					<?php esc_html_e( 'characters remaining', 'wp-appointments' ); ?>
				</p>
				<p class="wpappt-attachment-picker">
					<button type="button" class="button wpappt-attach-btn">
						<?php esc_html_e( 'Attach file', 'wp-appointments' ); ?>
					</button>
					<span class="wpappt-attachment-name" style="display:none;"></span>
					<button type="button" class="wpappt-attachment-clear" style="display:none;"
							aria-label="<?php esc_attr_e( 'Remove attachment', 'wp-appointments' ); ?>">&#x2715;</button>
					<input type="hidden" name="attachment_id" class="wpappt-attachment-id" value="">
				</p>
				<p>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Send Email', 'wp-appointments' ); ?>
					</button>
				</p>
			</form>
		</div>

	</div><!-- .wpappt-detail-grid -->
</div><!-- .wrap -->
