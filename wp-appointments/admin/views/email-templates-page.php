<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Templates admin view.
 *
 * Expected variables:
 *   @var array<string, array{label: string, fields: array<string, string>}> $registry
 */
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Email Templates', 'wp-appointments' ); ?></h1>
	<hr class="wp-header-end">

	<p class="description">
		<?php esc_html_e( 'Customise the text used in each transactional email. Leave a field blank to use the built-in default (shown as placeholder).', 'wp-appointments' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpappt-email-templates' ) ); ?>">
		<?php wp_nonce_field( 'wpappt_email_templates_save' ); ?>

		<div class="wpappt-tpl-accordion" style="margin-top: 16px;">
		<?php $first = true; foreach ( $registry as $slug => $config ) : ?>
			<details<?php echo $first ? ' open' : ''; ?>>
				<summary><?php echo esc_html( $config['label'] ); ?></summary>
				<div class="wpappt-tpl-inner">

					<!-- EN radio must come BEFORE its label; NL radio before its label;
					     both radios and both labels must be siblings of .wpappt-tabs-wrap
					     so the CSS ~ combinator can reach the panels. -->

					<input type="radio" class="wpappt-tab-radio wpappt-tab-radio--en"
					       name="wpappt_lang_tab_<?php echo esc_attr( $slug ); ?>"
					       id="wpappt_tab_<?php echo esc_attr( $slug ); ?>_en"
					       value="en" checked>
					<label class="wpappt-tab-label" for="wpappt_tab_<?php echo esc_attr( $slug ); ?>_en">
						<?php esc_html_e( 'English', 'wp-appointments' ); ?>
					</label>

					<input type="radio" class="wpappt-tab-radio wpappt-tab-radio--nl"
					       name="wpappt_lang_tab_<?php echo esc_attr( $slug ); ?>"
					       id="wpappt_tab_<?php echo esc_attr( $slug ); ?>_nl"
					       value="nl">
					<label class="wpappt-tab-label" for="wpappt_tab_<?php echo esc_attr( $slug ); ?>_nl">
						<?php esc_html_e( 'Dutch', 'wp-appointments' ); ?>
					</label>

					<div class="wpappt-tabs-wrap">
						<?php foreach ( [ 'en', 'nl' ] as $lang ) : ?>
						<div class="wpappt-tab-panel wpappt-tab-panel--<?php echo esc_attr( $lang ); ?>">
							<?php foreach ( $config['fields'] as $field => $defaults ) :
								$opt_key    = "wpappt_tpl_{$slug}_{$lang}_{$field}";
								$stored     = (string) get_option( $opt_key, '' );
								$input_name = "wpappt_tpl[{$slug}][{$lang}][{$field}]";
								$field_id   = "wpappt_tpl_{$slug}_{$lang}_{$field}";
								$label_text = ucfirst( $field );
								$placeholder = $defaults[ $lang ];
							?>
							<p>
								<label for="<?php echo esc_attr( $field_id ); ?>">
									<strong><?php echo esc_html( $label_text ); ?></strong>
								</label><br>
								<?php if ( 'subject' === $field ) : ?>
								<input type="text"
								       id="<?php echo esc_attr( $field_id ); ?>"
								       name="<?php echo esc_attr( $input_name ); ?>"
								       value="<?php echo esc_attr( $stored ); ?>"
								       placeholder="<?php echo esc_attr( $placeholder ); ?>"
								       class="large-text">
								<?php else : ?>
								<textarea id="<?php echo esc_attr( $field_id ); ?>"
								          name="<?php echo esc_attr( $input_name ); ?>"
								          rows="5"
								          placeholder="<?php echo esc_attr( $placeholder ); ?>"
								          class="large-text"><?php echo esc_textarea( $stored ); ?></textarea>
								<?php endif; ?>
							</p>
							<?php endforeach; ?>

						<?php if ( ! empty( $config['customer_facing'] ) ) :
							$att_id       = (int) get_option( "wpappt_tpl_{$slug}_{$lang}_attachment_id", 0 );
							$att_path     = $att_id > 0 ? get_attached_file( $att_id ) : false;
							$att_filename = ( $att_path && file_exists( $att_path ) ) ? wp_basename( $att_path ) : '';
						?>
						<p class="wpappt-attachment-picker">
							<strong><?php esc_html_e( 'Default attachment', 'wp-appointments' ); ?></strong><br>
							<button type="button" class="button wpappt-attach-btn">
								<?php esc_html_e( 'Attach file', 'wp-appointments' ); ?>
							</button>
							<span class="wpappt-attachment-name"
							      style="<?php echo $att_filename ? '' : 'display:none;'; ?>">
								<?php echo esc_html( $att_filename ); ?>
							</span>
							<button type="button" class="wpappt-attachment-clear"
							        style="<?php echo $att_filename ? '' : 'display:none;'; ?>"
							        aria-label="<?php esc_attr_e( 'Remove attachment', 'wp-appointments' ); ?>">&#x2715;</button>
							<input type="hidden"
							       name="wpappt_tpl[<?php echo esc_attr( $slug ); ?>][<?php echo esc_attr( $lang ); ?>][attachment_id]"
							       class="wpappt-attachment-id"
							       value="<?php echo esc_attr( (string) $att_id ); ?>">
						</p>
						<?php endif; ?>
						</div>
						<?php endforeach; ?>
					</div><!-- .wpappt-tabs-wrap -->

				</div><!-- .wpappt-tpl-inner -->
			</details>
		<?php $first = false; endforeach; ?>
		</div><!-- .wpappt-tpl-accordion -->

		<?php submit_button( __( 'Save Email Templates', 'wp-appointments' ) ); ?>
	</form>
</div><!-- .wrap -->
