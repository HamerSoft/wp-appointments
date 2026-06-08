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

/**
 * Renders a 24-hour time picker (two <select> elements + hidden input).
 *
 * The hidden input carries the actual form value so backend sanitisation is
 * unchanged.  The selects drive the display only and are synced to the hidden
 * input via admin.js.
 *
 * @param string $name     Name attribute for the hidden input.
 * @param string $value    Current time in HH:MM or HH:MM:SS format.
 * @param string $classes  Extra CSS classes applied to both selects.
 * @param bool   $disabled Whether the selects start disabled.
 */
function wpappt_time_picker( string $name, string $value, string $classes = '', bool $disabled = false ): void {
	$parts   = explode( ':', substr( $value, 0, 5 ) );
	$hour    = max( 0, min( 23, (int) ( $parts[0] ?? 0 ) ) );
	$minute  = max( 0, min( 59, (int) ( $parts[1] ?? 0 ) ) );
	// Snap displayed minute to nearest 5-minute step.
	$min_disp    = (int) round( $minute / 5 ) * 5;
	if ( $min_disp >= 60 ) {
		$min_disp = 55;
	}
	$dis    = $disabled ? ' disabled' : '';
	$cls    = trim( 'wpappt-time-input wpappt-time-sel ' . $classes );
	$hidden = sprintf( '%02d:%02d', $hour, $minute );
	?>
	<span class="wpappt-time-picker">
		<select class="<?php echo esc_attr( $cls ); ?>"
		        data-time-part="hour"<?php echo $dis; ?>>
			<?php for ( $h = 0; $h <= 23; $h++ ) :
				$hh = sprintf( '%02d', $h );
			?>
			<option value="<?php echo $hh; ?>"<?php selected( $h, $hour ); ?>><?php echo $hh; ?></option>
			<?php endfor; ?>
		</select>
		<span class="wpappt-time-sep">:</span>
		<select class="<?php echo esc_attr( $cls ); ?>"
		        data-time-part="minute"<?php echo $dis; ?>>
			<?php for ( $m = 0; $m <= 55; $m += 5 ) :
				$mm = sprintf( '%02d', $m );
			?>
			<option value="<?php echo $mm; ?>"<?php selected( $m, $min_disp ); ?>><?php echo $mm; ?></option>
			<?php endfor; ?>
		</select>
		<input type="hidden"
		       name="<?php echo esc_attr( $name ); ?>"
		       value="<?php echo esc_attr( $hidden ); ?>"
		       class="wpappt-time-value">
	</span>
	<?php
}

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
						<?php wpappt_time_picker(
							"availability[{$day}][start_time]",
							$start,
							'',
							! $checked
						); ?>
					</td>
					<td>
						<?php wpappt_time_picker(
							"availability[{$day}][end_time]",
							$end,
							'',
							! $checked
						); ?>
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
				<td>
					<?php echo esc_html( $slot['blocked_date'] ); ?>
					<?php if ( ! empty( $slot['series_id'] ) ) : ?>
						<span class="wpappt-series-badge"><?php esc_html_e( 'series', 'wp-appointments' ); ?></span>
					<?php endif; ?>
				</td>
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
					<?php if ( ! empty( $slot['series_id'] ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						  data-wpappt-confirm="<?php esc_attr_e( 'Remove all slots in this series?', 'wp-appointments' ); ?>">
						<input type="hidden" name="action"    value="wpappt_delete_blocked_series">
						<input type="hidden" name="series_id" value="<?php echo (int) $slot['series_id']; ?>">
						<?php wp_nonce_field( "wpappt_delete_blocked_series_{$slot['series_id']}" ); ?>
						<button type="submit" class="button-link wpappt-link-danger">
							<?php esc_html_e( 'Delete series', 'wp-appointments' ); ?>
						</button>
					</form>
					<?php endif; ?>
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
					<?php wpappt_time_picker( 'start_time', '09:00' ); ?>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'End Time', 'wp-appointments' ); ?></label></th>
				<td>
					<?php wpappt_time_picker( 'end_time', '10:00' ); ?>
				</td>
			</tr>
			<tr>
				<th><label for="wpappt-block-reason"><?php esc_html_e( 'Reason (optional)', 'wp-appointments' ); ?></label></th>
				<td>
					<input type="text" id="wpappt-block-reason" name="reason"
						   class="regular-text" maxlength="120">
				</td>
			</tr>
			<tr>
				<th><label for="wpappt-recurrence-type"><?php esc_html_e( 'Repeat', 'wp-appointments' ); ?></label></th>
				<td>
					<select id="wpappt-recurrence-type" name="recurrence_type">
						<option value="none"><?php esc_html_e( 'No repeat', 'wp-appointments' ); ?></option>
						<option value="daily"><?php esc_html_e( 'Daily', 'wp-appointments' ); ?></option>
						<option value="weekly"><?php esc_html_e( 'Weekly', 'wp-appointments' ); ?></option>
						<option value="monthly"><?php esc_html_e( 'Monthly', 'wp-appointments' ); ?></option>
					</select>
				</td>
			</tr>
			<tr id="wpappt-recurrence-daily" style="display:none;">
				<th><?php esc_html_e( 'Interval', 'wp-appointments' ); ?></th>
				<td>
					<?php esc_html_e( 'Every', 'wp-appointments' ); ?>
					<input type="number" name="daily_interval" id="wpappt-daily-interval"
						   min="1" value="1" style="width:60px;">
					<?php esc_html_e( 'day(s)', 'wp-appointments' ); ?>
				</td>
			</tr>
			<tr id="wpappt-recurrence-weekly" style="display:none;">
				<th><?php esc_html_e( 'Days', 'wp-appointments' ); ?></th>
				<td>
					<?php
					$weekday_labels = [
						1 => __( 'Mon', 'wp-appointments' ),
						2 => __( 'Tue', 'wp-appointments' ),
						3 => __( 'Wed', 'wp-appointments' ),
						4 => __( 'Thu', 'wp-appointments' ),
						5 => __( 'Fri', 'wp-appointments' ),
						6 => __( 'Sat', 'wp-appointments' ),
						0 => __( 'Sun', 'wp-appointments' ),
					];
					foreach ( $weekday_labels as $dow => $label ) : ?>
					<label style="margin-right:8px;">
						<input type="checkbox" name="weekly_days[]"
							   value="<?php echo esc_attr( $dow ); ?>"
							   class="wpappt-weekly-day">
						<?php echo esc_html( $label ); ?>
					</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr id="wpappt-recurrence-monthly" style="display:none;">
				<th><?php esc_html_e( 'Day', 'wp-appointments' ); ?></th>
				<td>
					<span id="wpappt-monthly-label">—</span>
				</td>
			</tr>
			<tr id="wpappt-recurrence-end-row" style="display:none;">
				<th><label for="wpappt-recurrence-end"><?php esc_html_e( 'End date', 'wp-appointments' ); ?></label></th>
				<td>
					<input type="date" id="wpappt-recurrence-end" name="recurrence_end_date">
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Add Blocked Slot', 'wp-appointments' ) ); ?>
	</form>

</div><!-- .wrap -->
