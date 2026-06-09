<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Email to customer: booking confirmed.
 *
 * @var array<string, mixed>      $booking
 * @var array<string, mixed>|null $service
 * @var string                    $site_name
 * @var string                    $reschedule_link  Empty until Step 7 wires in the token service.
 * @var string                    $body
 * @var string                    $closing
 */
?>
<p><?php
printf(
	esc_html__( 'Hi %s,', 'wp-appointments' ),
	esc_html( $booking['customer_name'] )
); ?></p>

<p><?php echo nl2br( esc_html( $body ) ); ?></p>

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

<?php if ( ! empty( $reschedule_link ) ) : ?>
<p style="margin-top:24px;padding:16px;background:#f9f9f9;border-left:4px solid #2c3e50;border-radius:2px;font-size:13px;">
	<?php esc_html_e( 'Need to change the time?', 'wp-appointments' ); ?>
	<a href="<?php echo esc_url( $reschedule_link ); ?>" style="color:#2c3e50;font-weight:bold;">
		<?php esc_html_e( 'Click here to reschedule', 'wp-appointments' ); ?>
	</a>
	<?php esc_html_e( '(link expires in 72 hours).', 'wp-appointments' ); ?>
</p>
<?php endif; ?>

<p style="margin-top:32px;color:#888888;font-size:13px;">
	<?php echo esc_html( $closing ); ?>
</p>
