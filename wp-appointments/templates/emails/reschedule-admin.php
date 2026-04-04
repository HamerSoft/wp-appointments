<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Email to admin: a customer has rescheduled.
 *
 * @var array<string, mixed>      $booking
 * @var array<string, mixed>|null $service
 * @var string                    $site_name
 * @var string                    $admin_panel_url
 */
?>
<p><?php
printf(
	esc_html__( '%s has rescheduled their appointment. The booking status has been reset to Pending.', 'wp-appointments' ),
	esc_html( $booking['customer_name'] )
); ?></p>

<table cellpadding="0" cellspacing="0" border="0"
       style="width:100%;margin:24px 0;border:1px solid #e0e0e0;border-radius:4px;border-collapse:collapse;">
	<?php
	$rows = [
		__( 'Booking ID', 'wp-appointments' ) => '#' . (int) $booking['id'],
		__( 'Service',    'wp-appointments' ) => $service ? $service['name'] : '—',
		__( 'New Date',   'wp-appointments' ) => $booking['appointment_date'],
		__( 'New Time',   'wp-appointments' ) => substr( $booking['start_time'], 0, 5 ) . ' – ' . substr( $booking['end_time'], 0, 5 ),
		__( 'Email',      'wp-appointments' ) => $booking['customer_email'],
		__( 'Phone',      'wp-appointments' ) => $booking['customer_phone'],
	];

	$alt = false;
	foreach ( $rows as $label => $value ) :
		$style = $alt ? 'background:#f9f9f9;' : '';
		$alt   = ! $alt;
	?>
	<tr style="<?php echo esc_attr( $style ); ?>">
		<td style="padding:10px 16px;font-weight:bold;width:40%;border-bottom:1px solid #e0e0e0;">
			<?php echo esc_html( $label ); ?>
		</td>
		<td style="padding:10px 16px;border-bottom:1px solid #e0e0e0;">
			<?php echo esc_html( $value ); ?>
		</td>
	</tr>
	<?php endforeach; ?>
</table>

<p>
	<a href="<?php echo esc_url( $admin_panel_url ); ?>"
	   style="display:inline-block;padding:12px 24px;background:#2c3e50;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'Review &amp; Confirm Booking', 'wp-appointments' ); ?>
	</a>
</p>
