<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Email to customer: booking cancelled.
 *
 * @var array<string, mixed>      $booking
 * @var array<string, mixed>|null $service
 * @var string                    $site_name
 * @var string                    $booking_page_url
 */
?>
<p><?php
printf(
	esc_html__( 'Hi %s,', 'wp-appointments' ),
	esc_html( $booking['customer_name'] )
); ?></p>

<p><?php esc_html_e( 'We are sorry to let you know that the following booking has been cancelled.', 'wp-appointments' ); ?></p>

<table cellpadding="0" cellspacing="0" border="0"
       style="width:100%;margin:24px 0;border:1px solid #e0e0e0;border-radius:4px;border-collapse:collapse;">
	<tr style="background:#f9f9f9;">
		<td style="padding:10px 16px;font-weight:bold;width:40%;border-bottom:1px solid #e0e0e0;">
			<?php esc_html_e( 'Service', 'wp-appointments' ); ?>
		</td>
		<td style="padding:10px 16px;border-bottom:1px solid #e0e0e0;">
			<?php echo $service ? esc_html( $service['name'] ) : esc_html__( '—', 'wp-appointments' ); ?>
		</td>
	</tr>
	<tr>
		<td style="padding:10px 16px;font-weight:bold;border-bottom:1px solid #e0e0e0;">
			<?php esc_html_e( 'Date', 'wp-appointments' ); ?>
		</td>
		<td style="padding:10px 16px;border-bottom:1px solid #e0e0e0;">
			<?php echo esc_html( $booking['appointment_date'] ); ?>
		</td>
	</tr>
	<tr style="background:#f9f9f9;">
		<td style="padding:10px 16px;font-weight:bold;">
			<?php esc_html_e( 'Time', 'wp-appointments' ); ?>
		</td>
		<td style="padding:10px 16px;">
			<?php
			echo esc_html( substr( $booking['start_time'], 0, 5 ) )
				. ' – '
				. esc_html( substr( $booking['end_time'], 0, 5 ) );
			?>
		</td>
	</tr>
</table>

<p>
	<a href="<?php echo esc_url( $booking_page_url ); ?>"
	   style="display:inline-block;padding:12px 24px;background:#2c3e50;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'Book a new appointment', 'wp-appointments' ); ?>
	</a>
</p>

<p style="margin-top:32px;color:#888888;font-size:13px;">
	<?php esc_html_e( 'If you have any questions, simply reply to this email.', 'wp-appointments' ); ?>
</p>
