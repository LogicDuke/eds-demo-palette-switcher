<?php
/**
 * Frontend: no-flash bootstrap, palette CSS, and the switcher panel.
 *
 * Everything printed here is identical for every visitor, so pages stay
 * full-page-cache safe. The visitor's choice lives only in their localStorage.
 *
 * @package eds-demo-palette-switcher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', '_eds_dps_print_bootstrap', 1 );
add_action( 'wp_head', '_eds_dps_print_palette_css', 99 );
add_action( 'wp_enqueue_scripts', '_eds_dps_enqueue_assets' );
add_action( 'wp_footer', '_eds_dps_print_panel' );

/**
 * The integration when the frontend should run, otherwise null (plugin stays inert).
 *
 * @internal
 * @return array|null
 */
function _eds_dps_frontend_integration() {
	// The Customizer preview shows the palette being edited; a stored visitor choice must not mask it.
	if ( ! _eds_dps_is_enabled() || is_customize_preview() ) {
		return null;
	}
	$integration = eds_dps_get_integration();
	return ( null !== $integration && ! empty( $integration['palettes'] ) ) ? $integration : null;
}

/**
 * localStorage key for an integration. IDs are strict keys, so the result is safe as-is.
 *
 * @internal
 * @param string $integration_id
 * @return string
 */
function _eds_dps_storage_key( $integration_id ) {
	return 'eds-dps:' . $integration_id;
}

/**
 * Data shared by the bootstrap and the controller.
 *
 * @internal
 * @param array $integration
 * @return array
 */
function _eds_dps_client_config( $integration ) {
	$palettes = array();
	foreach ( $integration['palettes'] as $palette ) {
		$palettes[ $palette['id'] ] = $palette['name'];
	}
	return array(
		'key'            => _eds_dps_storage_key( $integration['integration_id'] ),
		'defaultPalette' => $integration['default_palette'],
		// Cast keeps it a JSON object even for numeric-looking IDs.
		'palettes'       => (object) $palettes,
	);
}

/**
 * Inline head script: apply a stored, still-registered palette before first paint.
 * Never throws; does nothing if storage is unavailable or the value is unknown.
 *
 * @internal
 * @param array $integration
 * @return string
 */
function _eds_dps_bootstrap_script( $integration ) {
	$json = wp_json_encode( _eds_dps_client_config( $integration ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	return 'window.edsDemoPalette=' . $json . ';'
		. '(function(c){try{var v=window.localStorage.getItem(c.key);'
		. 'if(v&&Object.prototype.hasOwnProperty.call(c.palettes,v)){document.documentElement.setAttribute("data-eds-demo-palette",v);}'
		. '}catch(e){}})(window.edsDemoPalette);';
}

/**
 * One rule per palette. Built only from validated registration data: IDs are
 * strict keys, names match ^--[A-Za-z0-9_-]+\z, values passed the safe-value check.
 *
 * @internal
 * @param array $integration
 * @return string
 */
function _eds_dps_palette_css( $integration ) {
	$css = '';
	foreach ( $integration['palettes'] as $palette ) {
		$css .= 'html[data-eds-demo-palette="' . $palette['id'] . '"]{';
		foreach ( $palette['vars'] as $property => $value ) {
			$css .= $property . ':' . $value . ';';
		}
		$css .= '}';
	}
	return $css;
}

/**
 * @internal
 */
function _eds_dps_print_bootstrap() {
	$integration = _eds_dps_frontend_integration();
	if ( null !== $integration ) {
		wp_print_inline_script_tag( _eds_dps_bootstrap_script( $integration ), array( 'id' => 'eds-dps-bootstrap' ) );
	}
}

/**
 * Printed late in <head> so equal-specificity theme rules cannot win by source order.
 *
 * @internal
 */
function _eds_dps_print_palette_css() {
	$integration = _eds_dps_frontend_integration();
	if ( null !== $integration ) {
		// Safe: every part was validated at registration (see _eds_dps_palette_css()).
		echo '<style id="eds-dps-palettes">' . _eds_dps_palette_css( $integration ) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * @internal
 */
function _eds_dps_enqueue_assets() {
	if ( null === _eds_dps_frontend_integration() ) {
		return;
	}
	$url = plugin_dir_url( EDS_DPS_FILE );
	wp_enqueue_style( 'eds-dps', $url . 'assets/css/demo-palette-switcher.css', array(), EDS_DPS_VERSION );
	wp_enqueue_script( 'eds-dps', $url . 'assets/js/demo-palette-switcher.js', array(), EDS_DPS_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
}

/**
 * The switcher panel. Hidden until the controller script runs, so it never shows dead buttons.
 *
 * @internal
 */
function _eds_dps_print_panel() {
	$integration = _eds_dps_frontend_integration();
	if ( null === $integration ) {
		return;
	}
	?>
	<div id="eds-dps" class="eds-dps" role="region" aria-label="<?php esc_attr_e( 'Demo color palettes', 'eds-demo-palette-switcher' ); ?>" hidden>
		<p class="eds-dps__current" aria-live="polite">
			<?php esc_html_e( 'Current palette:', 'eds-demo-palette-switcher' ); ?> <strong data-eds-dps-current></strong>
		</p>
		<div class="eds-dps__list" role="group" aria-label="<?php esc_attr_e( 'Choose a palette', 'eds-demo-palette-switcher' ); ?>">
			<?php foreach ( $integration['palettes'] as $palette ) : ?>
				<button type="button" class="eds-dps__palette" data-eds-dps-palette="<?php echo esc_attr( $palette['id'] ); ?>" aria-pressed="false">
					<span class="eds-dps__swatches" aria-hidden="true">
						<?php foreach ( $palette['swatches'] as $swatch ) : ?>
							<span class="eds-dps__swatch" style="background-color:<?php echo esc_attr( $swatch ); ?>"></span>
						<?php endforeach; ?>
					</span>
					<span class="eds-dps__name"><?php echo esc_html( $palette['name'] ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
		<button type="button" class="eds-dps__reset" data-eds-dps-reset><?php esc_html_e( 'Reset to Default', 'eds-demo-palette-switcher' ); ?></button>
	</div>
	<?php
}
