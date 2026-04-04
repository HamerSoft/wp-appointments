<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Base email shell.
 *
 * Expected variables (extracted by WPAPPT_Service_Email::render):
 *   @var string $site_name  Blog name.
 *   @var string $content    Pre-rendered HTML body from the specific template.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;font-size:15px;color:#333333;">

<table width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<td align="center" style="padding:24px 16px;">

			<table width="600" cellpadding="0" cellspacing="0" border="0"
			       style="background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);">

				<!-- Header -->
				<tr>
					<td style="background:#2c3e50;padding:24px 32px;">
						<span style="font-size:20px;font-weight:bold;color:#ffffff;text-decoration:none;">
							<?php echo esc_html( $site_name ); ?>
						</span>
					</td>
				</tr>

				<!-- Body -->
				<tr>
					<td style="padding:32px 32px 24px;line-height:1.65;">
						<?php
						// $content is already HTML-escaped by the specific template.
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $content;
						?>
					</td>
				</tr>

				<!-- Footer -->
				<tr>
					<td style="padding:16px 32px;background:#f9f9f9;border-top:1px solid #eeeeee;font-size:12px;color:#888888;">
						<?php echo esc_html( $site_name ); ?> &mdash;
						<?php esc_html_e( 'Booking Management', 'wp-appointments' ); ?>
					</td>
				</tr>

			</table>
		</td>
	</tr>
</table>

</body>
</html>
