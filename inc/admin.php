<?php
/**
 * Admin: the single "Enable Demo Palette Switcher" setting on Settings > General.
 *
 * @package eds-demo-palette-switcher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', '_eds_dps_register_setting' );

/**
 * Register the option with the Settings API. Saving goes through options.php,
 * which requires manage_options for the General settings page.
 *
 * @internal
 */
function _eds_dps_register_setting() {
	register_setting(
		'general',
		'eds_dps_enabled',
		array(
			'type'              => 'string',
			'default'           => '0',
			'sanitize_callback' => '_eds_dps_sanitize_enabled',
			'show_in_rest'      => false,
		)
	);

	add_settings_field(
		'eds_dps_enabled',
		esc_html__( 'Demo Palette Switcher', 'eds-demo-palette-switcher' ),
		'_eds_dps_render_enabled_field',
		'general'
	);
}

/**
 * Stored as '1' or '0'. An unchecked checkbox posts nothing, which sanitizes to '0'.
 *
 * @internal
 * @param mixed $value
 * @return string
 */
function _eds_dps_sanitize_enabled( $value ) {
	return ( '1' === $value || 1 === $value || true === $value ) ? '1' : '0';
}

/**
 * Whether the switcher is enabled. Defaults to off.
 *
 * @internal
 * @return bool
 */
function _eds_dps_is_enabled() {
	return '1' === get_option( 'eds_dps_enabled', '0' );
}

/**
 * @internal
 */
function _eds_dps_render_enabled_field() {
	?>
	<label for="eds_dps_enabled">
		<input type="checkbox" id="eds_dps_enabled" name="eds_dps_enabled" value="1" <?php checked( _eds_dps_is_enabled() ); ?> />
		<?php esc_html_e( 'Enable Demo Palette Switcher', 'eds-demo-palette-switcher' ); ?>
	</label>
	<p class="description">
		<?php esc_html_e( 'Lets visitors preview the color palettes registered by the active theme. Visitor choices are stored only in their own browser and never change site settings.', 'eds-demo-palette-switcher' ); ?>
	</p>
	<?php
}
