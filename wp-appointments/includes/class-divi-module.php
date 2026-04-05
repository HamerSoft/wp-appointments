<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Divi builder module for the booking widget.
 *
 * Registered on the `et_builder_ready` action (fired by Divi after all its
 * builder classes are loaded). Falls back gracefully when Divi is not active —
 * the action simply never fires.
 *
 * The module renders the same container div as the [wpappt_booking] shortcode;
 * the JS widget attaches itself to that element.
 */
class WPAPPT_Divi_Module extends ET_Builder_Module {

	public $name       = 'Booking Widget';
	public $slug       = 'wpappt_booking';
	public $vb_support = 'off';

	/**
	 * Called automatically by the ET_Builder_Module constructor.
	 * Defines module metadata and settings.
	 */
	public function init(): void {
		$this->name             = esc_html__( 'Booking Widget', 'wp-appointments' );
		$this->slug             = 'wpappt_booking';
		$this->vb_support       = 'off';
		$this->main_css_element = '#wpappt-booking-widget';

		$this->settings_modal_toggles = [
			'general' => [
				'toggles' => [
					'main_content' => esc_html__( 'Booking Widget', 'wp-appointments' ),
				],
			],
		];
	}

	/**
	 * Module fields shown in the Divi settings panel.
	 *
	 * No configuration is needed — the widget is self-contained — so this
	 * returns an empty array.
	 *
	 * @return array<string, mixed>
	 */
	public function get_fields(): array {
		return [];
	}

	/**
	 * Render the module on the frontend and in the Visual Builder.
	 *
	 * @param  array<string, mixed> $attrs       Module attribute values.
	 * @param  string|null          $content     Inner content (unused).
	 * @param  string               $render_slug Module slug.
	 * @return string HTML output.
	 */
	public function render( $attrs, $content, $render_slug ): string {
		if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			return '<div style="padding:2em;text-align:center;border:2px dashed #ccc;color:#888;">Booking Widget — preview on the frontend</div>';
		}
		return '<div id="wpappt-booking-widget"></div>';
	}
}
