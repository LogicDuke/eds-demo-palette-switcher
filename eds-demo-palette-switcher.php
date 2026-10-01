<?php
/**
 * Plugin Name:       EDS Demo Palette Switcher
 * Description:       Lets visitors of Elite Digital Solutions demo sites temporarily preview the color palettes registered by the active EDS theme.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Elite Digital Solutions
 * License:           GPL-2.0-or-later
 * Text Domain:       eds-demo-palette-switcher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EDS_DPS_VERSION', '0.1.0' );

/**
 * Public theme API: register the active theme's palettes.
 *
 * The last valid registration wins. An invalid registration stores nothing,
 * keeps any previous valid registration, and returns false.
 *
 * @param array $config See README.md for the contract.
 * @return bool
 */
function eds_dps_register_theme_palettes( $config ) {
	return _eds_dps_integration( $config );
}

/**
 * Public API: the validated integration, or null when the plugin should stay inert.
 *
 * @return array|null
 */
function eds_dps_get_integration() {
	return _eds_dps_integration();
}

/**
 * Internal store. Not part of the public contract.
 *
 * Called with no arguments it reads; called with one argument it validates and
 * writes. Every write path validates, so stored data cannot bypass validation.
 *
 * @internal
 * @param mixed $config Optional. A raw integration definition to store.
 * @return array|null|bool The stored integration on read; whether it was stored on write.
 */
function _eds_dps_integration( $config = null ) {
	static $integration = null;

	if ( 0 === func_num_args() ) {
		return $integration;
	}

	$validated = _eds_dps_validate_integration( $config );
	if ( null === $validated ) {
		return false;
	}
	$integration = $validated;
	return true;
}

/**
 * A valid ID is a string that sanitize_key() leaves unchanged. IDs are never rewritten.
 *
 * @internal
 * @param mixed $id
 * @return bool
 */
function _eds_dps_is_valid_id( $id ) {
	return is_string( $id ) && '' !== $id && sanitize_key( $id ) === $id;
}

/**
 * Normalize a raw integration definition. Returns null if it is unusable.
 *
 * @internal
 * @param mixed $config
 * @return array|null
 */
function _eds_dps_validate_integration( $config ) {
	if ( ! is_array( $config ) || ! isset( $config['palettes'] ) || ! is_array( $config['palettes'] ) ) {
		return null;
	}
	if ( ! isset( $config['integration_id'], $config['default_palette'] )
		|| ! _eds_dps_is_valid_id( $config['integration_id'] )
		|| ! _eds_dps_is_valid_id( $config['default_palette'] ) ) {
		return null;
	}

	$palettes = array();
	foreach ( $config['palettes'] as $raw ) {
		$palette = _eds_dps_validate_palette( $raw );
		// Invalid palettes and later duplicates of an ID are dropped.
		if ( null !== $palette && ! isset( $palettes[ $palette['id'] ] ) ) {
			$palettes[ $palette['id'] ] = $palette;
		}
	}

	// The configured default must survive validation; never guess a replacement.
	if ( ! isset( $palettes[ $config['default_palette'] ] ) ) {
		return null;
	}

	return array(
		'integration_id'  => $config['integration_id'],
		'default_palette' => $config['default_palette'],
		'palettes'        => array_values( $palettes ),
	);
}

/**
 * Normalize one palette. Any invalid part rejects the whole palette so a preview is never partial.
 *
 * @internal
 * @param mixed $raw
 * @return array|null
 */
function _eds_dps_validate_palette( $raw ) {
	if ( ! is_array( $raw ) || ! isset( $raw['id'] ) || ! _eds_dps_is_valid_id( $raw['id'] ) ) {
		return null;
	}

	$name = isset( $raw['name'] ) && is_string( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '';
	if ( '' === $name ) {
		return null;
	}

	if ( empty( $raw['swatches'] ) || ! is_array( $raw['swatches'] ) ) {
		return null;
	}
	$swatches = array();
	foreach ( $raw['swatches'] as $swatch ) {
		// Hex only in v0.1.0; sanitize_hex_color() rejects 8-digit (#rrggbbaa) hex.
		$hex = is_string( $swatch ) ? sanitize_hex_color( $swatch ) : null;
		if ( empty( $hex ) ) {
			return null;
		}
		$swatches[] = $hex;
	}

	if ( empty( $raw['vars'] ) || ! is_array( $raw['vars'] ) ) {
		return null;
	}
	$vars = array();
	foreach ( $raw['vars'] as $property => $value ) {
		if ( ! is_string( $property ) || ! preg_match( '/^--[A-Za-z0-9_-]+\z/', $property ) ) {
			return null;
		}
		if ( ! is_string( $value ) || ! _eds_dps_is_safe_css_value( $value ) ) {
			return null;
		}
		$vars[ $property ] = trim( $value );
	}

	return array(
		'id'       => $raw['id'],
		'name'     => $name,
		'swatches' => $swatches,
		'vars'     => $vars,
	);
}

/**
 * Whether a value is a safe color expression: hex, keywords (named colors,
 * transparent, currentColor), numbers/units, and only allowlisted functions.
 *
 * The character allowlist excludes ; { } < > quotes \ ! : @ so a value can never
 * leave its declaration. Parentheses must balance so a value cannot swallow
 * following CSS.
 *
 * @internal
 * @param string $value
 * @return bool
 */
function _eds_dps_is_safe_css_value( $value ) {
	static $functions = array( 'rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'color', 'color-mix', 'var', 'calc', 'min', 'max', 'clamp' );

	$value = trim( $value );
	if ( '' === $value || strlen( $value ) > 200 ) {
		return false;
	}
	if ( ! preg_match( '/^[A-Za-z0-9#%.,()\s\/+*-]+\z/', $value ) ) {
		return false;
	}
	if ( false !== strpos( $value, '/*' ) || false !== strpos( $value, '*/' ) ) {
		return false;
	}

	// Every "name(" must be allowlisted. A bare "(" is a calc() grouping.
	preg_match_all( '/([A-Za-z0-9_-]*)\s*\(/', $value, $matches );
	foreach ( $matches[1] as $name ) {
		if ( '' !== $name && ! in_array( strtolower( $name ), $functions, true ) ) {
			return false;
		}
	}

	$depth = 0;
	foreach ( str_split( $value ) as $char ) {
		if ( '(' === $char ) {
			++$depth;
		} elseif ( ')' === $char && --$depth < 0 ) {
			return false;
		}
	}
	return 0 === $depth;
}
