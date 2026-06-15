<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Templates admin page — handles save and renders the view.
 */
class WPAPPT_Admin_Email_Templates_Page {

	public function handle_save(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-appointments' ), 403 );
		}
		if ( ! isset( $_POST['wpappt_tpl'] ) || ! is_array( $_POST['wpappt_tpl'] ) ) {
			return;
		}
		check_admin_referer( 'wpappt_email_templates_save' );
		$this->save_posted_data( (array) $_POST['wpappt_tpl'] );
		wp_redirect( add_query_arg(
			'wpappt_notice',
			'email_template_saved',
			admin_url( 'admin.php?page=wpappt-email-templates' )
		) );
		exit;
	}

	public function save_posted_data( array $input ): void {
		$registry = WPAPPT_Service_Email_Template_Store::get_registry();
		$langs    = [ 'en', 'nl' ];

		foreach ( $registry as $slug => $config ) {
			if ( ! isset( $input[ $slug ] ) ) {
				continue;
			}
			foreach ( $langs as $lang ) {
				if ( ! isset( $input[ $slug ][ $lang ] ) ) {
					continue;
				}
				foreach ( array_keys( $config['fields'] ) as $field ) {
					$value     = $input[ $slug ][ $lang ][ $field ] ?? '';
					$sanitized = 'subject' === $field
						? sanitize_text_field( $value )
						: sanitize_textarea_field( $value );
					update_option(
						"wpappt_tpl_{$slug}_{$lang}_{$field}",
						$sanitized
					);
				}
			}
		}

		foreach ( $registry as $slug => $config ) {
			if ( empty( $config['customer_facing'] ) ) {
				continue;
			}
			foreach ( $langs as $lang ) {
				$id = absint( $input[ $slug ][ $lang ]['attachment_id'] ?? 0 );
				update_option( "wpappt_tpl_{$slug}_{$lang}_attachment_id", $id );
			}
		}
	}

	public function render(): void {
		$registry = WPAPPT_Service_Email_Template_Store::get_registry();
		include WPAPPT_PLUGIN_DIR . 'admin/views/email-templates-page.php';
	}
}
