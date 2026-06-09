<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Follow-up email from admin to customer.
 *
 * @var array<string, mixed>      $booking
 * @var array<string, mixed>|null $service
 * @var string                    $site_name
 * @var string                    $message   HTML-escaped, newlines converted to <br>.
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

<blockquote style="margin:24px 0;padding:16px 20px;background:#f9f9f9;border-left:4px solid #2c3e50;border-radius:2px;font-style:italic;color:#555555;">
	<?php
	// $message is already run through esc_html() + nl2br() in the email service.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $message;
	?>
</blockquote>

<p style="font-size:13px;color:#888888;">
	<?php
	printf(
		esc_html__( 'This message relates to your %s appointment on %s at %s.', 'wp-appointments' ),
		$service ? esc_html( $service['name'] ) : esc_html__( 'massage', 'wp-appointments' ),
		esc_html( $booking['appointment_date'] ),
		esc_html( substr( $booking['start_time'], 0, 5 ) )
	);
	?>
</p>

<p style="margin-top:32px;color:#888888;font-size:13px;">
	<?php echo esc_html( $closing ); ?>
</p>
