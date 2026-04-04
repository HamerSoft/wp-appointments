<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Availability management view.
 *
 * Expected variables:
 *   @var array<int, array<string, mixed>> $availability  All availability rows from DB.
 *   @var array<int, array<string, mixed>> $blocked_slots Upcoming blocked slots from DB.
 */

$day_names = [
	0 => __( 'Sunday',    'wp-appointments' ),
	1 => __( 'Monday',    'wp-appointments' ),
	2 => __( 'Tuesday',   'wp-appointments' ),
	3 => __( 'Wednesday', 'wp-appointments' ),
	4 => __( 'Thursday',  'wp-appointments' ),
	5 => __( 'Friday',    'wp-appointments' ),
	6 => __( 'Saturday',  'wp-appointments' ),
];

// Build a lookup: day_of_week → first availability row for that day.
$avail_by_day = [];
foreach ( $availability as $row ) {
	$day = (int) $row['day_of_week'];
	if ( ! isset( $avail_by_day[ $day ] ) ) {
		$avail_by_day[ $day ] = $row;
	}
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Availability', 'wp-appointments' ); ?></h1>
	<hr class="wp-header-end">

	<!-- -------------------------------------------------------------------- -->
	<!-- Weekly template                                                       -->
	<!-- -------------------------------------------------------------------- -->
	<h2><?php esc_html_e( 'Weekly Template', 'wp-appointments' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Set your regular opening hours. Days left unchecked will show no available slots.', 'wp-appointments' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpappt_save_availability">
		<?php wp_nonce_field( 'wpappt_save_availability' ); ?>

		<table class="wp-list-table widefat fixed" id="wpappt-availability-table">
			<thead>
				<tr>
					<th style="width:30px;"><?php esc_html_e( 'Open', 'wp-appointments' ); ?></th>
					<th><?php esc_html_e( 'Day', 'wp-appointments' ); ?></th>
					<th><?php esc_html_e( 'Start Time', 'wp-appointments' ); ?></th>
					<th><?php esc_html_e( 'End Time', 'wp-appointments' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php for ( $day = 0; $day <= 6; $day++ ) :
					$row       = $avail_by_day[ $day ] ?? null;
					$checked   = $row && $row['is_available'];
					$start     = $row ? $row['start_time'] : '09:00:00';
					$end       = $row ? $row['end_time']   : '17:00:00';
				?>
				<tr class="wpappt-avail-row<?php echo $checked ? '' : ' wpappt-avail-row--disabled'; ?>">
					<td>
						<input type="checkbox"
							   name="availability[<?php echo $day; ?>][is_available]"
							   value="1"
							   class="wpappt-avail-toggle"
							   <?php checked( $checked ); ?>>
					</td>
					<td><strong><?php echo esc_html( $day_names[ $day ] ); ?></strong></td>
					<td>
						<input type="time"
							   name="availability[<?php echo $day; ?>][start_time]"
							   value="<?php echo esc_attr( substr( $start, 0, 5 ) ); ?>"
							   class="wpappt-time-input"
							   <?php echo $checked ? '' : 'disabled'; ?>>
					</td>
					<td>
						<input type="time"
							   name="availability[<?php echo $day; ?>][end_time]"
							   value="<?php echo esc_attr( substr( $end, 0, 5 ) ); ?>"
							   class="wpappt-time-input"
							   <?php echo $checked ? '' : 'disabled'; ?>>
					</td>
				</tr>
				<?php endfor; ?>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Availability', 'wp-appointments' ) ); ?>
	</form>

	<hr>

	<!-- -------------------------------------------------------------------- -->
	<!-- Blocked slots                                                         -->
	<!-- -------------------------------------------------------------------- -->
	<h2><?php esc_html_e( 'Blocked Slots', 'wp-appointments' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Block specific dates or time ranges (e.g. holidays, personal commitments).', 'wp-appointments' ); ?>
	</p>

	<?php if ( ! empty( $blocked_slots ) ) : ?>
	<table class="wp-list-table widefat fixed striped" style="margin-bottom: 24px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'wp-appointments' ); ?></th>
				<th><?php esc_html_e( 'Start', 'wp-appointments' ); ?></th>
				<th><?php esc_html_e( 'End', 'wp-appointments' ); ?></th>
				<th><?php esc_html_e( 'Reason', 'wp-appointments' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-appointments' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $blocked_slots as $slot ) : ?>
			<tr>
				<td><?php echo esc_html( $slot['blocked_date'] ); ?></td>
				<td><?php echo esc_html( substr( $slot['start_time'], 0, 5 ) ); ?></td>
				<td><?php echo esc_html( substr( $slot['end_time'],   0, 5 ) ); ?></td>
				<td><?php echo esc_html( $slot['reason'] ?? '—' ); ?></td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						  data-wpappt-confirm="<?php esc_attr_e( 'Remove this blocked slot?', 'wp-appointments' ); ?>">
						<input type="hidden" name="action"          value="wpappt_delete_blocked_slot">
						<input type="hidden" name="blocked_slot_id" value="<?php echo (int) $slot['id']; ?>">
						<?php wp_nonce_field( "wpappt_delete_blocked_slot_{$slot['id']}" ); ?>
						<button type="submit" class="button-link wpappt-link-danger">
							<?php esc_html_e( 'Remove', 'wp-appointments' ); ?>
						</button>
					</form>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Add Blocked Slot', 'wp-appointments' ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpappt_add_blocked_slot">
		<?php wp_nonce_field( 'wpappt_add_blocked_slot' ); ?>

		<table class="form-table">
			<tr>
				<th><label for="wpappt-block-date"><?php esc_html_e( 'Date', 'wp-appointments' ); ?></label></th>
				<td>
					<input type="date" id="wpappt-block-date" name="blocked_date"
						   class="regular-text" required>
				</td>
			</tr>
			<tr>
				<th><label for="wpappt-block-start"><?php esc_html_e( 'Start Time', 'wp-appointments' ); ?></label></th>
				<td>
					<input type="time" id="wpappt-block-start" name="start_time" required>
				</td>
			</tr>
			<tr>
				<th><label for="wpappt-block-end"><?php esc_html_e( 'End Time', 'wp-appointments' ); ?></label></th>
				<td>
					<input type="time" id="wpappt-block-end" name="end_time" required>
				</td>
			</tr>
			<tr>
				<th><label for="wpappt-block-reason"><?php esc_html_e( 'Reason (optional)', 'wp-appointments' ); ?></label></th>
				<td>
					<input type="text" id="wpappt-block-reason" name="reason"
						   class="regular-text" maxlength="120">
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Add Blocked Slot', 'wp-appointments' ) ); ?>
	</form>

</div><!-- .wrap -->
