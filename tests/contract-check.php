<?php
/**
 * Contract check. Run: php tests/contract-check.php
 * Exits non-zero on the first failure. Stubs only the WordPress functions the plugin uses.
 */

define( 'ABSPATH', __DIR__ );

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}
function sanitize_text_field( $str ) {
	return trim( strip_tags( $str ) );
}
function sanitize_hex_color( $color ) {
	if ( '' === $color ) {
		return '';
	}
	return preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ? $color : null;
}

require dirname( __DIR__ ) . '/eds-demo-palette-switcher.php';

$checks = 0;
function check( $condition, $label ) {
	global $checks;
	++$checks;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $label\n" );
		exit( 1 );
	}
	echo "ok   $label\n";
}

function palette( $id, $overrides = array() ) {
	return array_merge(
		array(
			'id'       => $id,
			'name'     => ucfirst( (string) $id ),
			'swatches' => array( '#ffffff', '#111111' ),
			'vars'     => array(
				'--example-background' => '#ffffff',
				'--example-text'       => '#111111',
			),
		),
		$overrides
	);
}

function config( $palettes, $default = 'default', $integration_id = 'example-theme' ) {
	return array(
		'integration_id'  => $integration_id,
		'default_palette' => $default,
		'palettes'        => $palettes,
	);
}

function ids( $integration ) {
	return array_column( $integration['palettes'], 'id' );
}

function value_ok( $value ) {
	return _eds_dps_validate_integration( config( array( palette( 'default', array( 'vars' => array( '--x' => $value ) ) ) ) ) ) !== null;
}

// --- Registration lifecycle -------------------------------------------------

check( null === eds_dps_get_integration(), 'no registration => inert' );

check( true === eds_dps_register_theme_palettes( config( array( palette( 'default' ) ) ) ), 'README example registers' );
$i = eds_dps_get_integration();
check( 'example-theme' === $i['integration_id'] && 'default' === $i['default_palette'], 'root keys preserved' );
check( '#111111' === $i['palettes'][0]['vars']['--example-text'], 'palette normalized' );

check( false === eds_dps_register_theme_palettes( array() ), 'failed registration returns false' );
check( 'example-theme' === eds_dps_get_integration()['integration_id'], 'failed registration preserves previous valid one' );

check( true === eds_dps_register_theme_palettes( config( array( palette( 'light' ), palette( 'dark' ) ), 'dark', 'child-theme' ) ), 'second valid registration accepted' );
$i = eds_dps_get_integration();
check( 'child-theme' === $i['integration_id'] && 'dark' === $i['default_palette'], 'second valid registration replaces first' );

check( false === eds_dps_register_theme_palettes( null ), 'null registration rejected' );
check( 'child-theme' === eds_dps_get_integration()['integration_id'], 'null registration preserves previous' );

$GLOBALS['eds_dps_integration'] = array( 'integration_id' => 'hijack' );
check( 'child-theme' === eds_dps_get_integration()['integration_id'], 'global variable cannot replace registration' );

// --- Strict IDs -------------------------------------------------------------

foreach ( array( 'Example-Theme', 'example theme', 'example.theme', 'example!', '' ) as $bad ) {
	check( null === _eds_dps_validate_integration( config( array( palette( 'default' ) ), 'default', $bad ) ), "integration_id rejected: '$bad'" );
}
foreach ( array( 'Default', 'default ', 'de fault', 'default!' ) as $bad ) {
	check( null === _eds_dps_validate_integration( config( array( palette( 'default' ) ), $bad ) ), "malformed default rejected, not normalized: '$bad'" );
}
foreach ( array( 'Ocean', 'oce an', 'ocean.', 'ocean!', '' ) as $bad ) {
	$i = _eds_dps_validate_integration( config( array( palette( 'default' ), palette( $bad ) ) ) );
	check( array( 'default' ) === ids( $i ), "palette id rejected: '$bad'" );
}
$i = _eds_dps_validate_integration( config( array( palette( 'default' ), palette( 'Ocean', array( 'name' => 'Upper' ) ), palette( 'ocean', array( 'name' => 'Lower' ) ) ) ) );
check( array( 'default', 'ocean' ) === ids( $i ) && 'Lower' === $i['palettes'][1]['name'], 'Ocean/ocean do not collapse; only the valid id survives' );

// --- Root-level rejection and wrong types -----------------------------------

$wrong_roots = array(
	'non-array config'          => 'nope',
	'short root keys'           => array( 'id' => 'x', 'default' => 'default', 'palettes' => array( palette( 'default' ) ) ),
	'integration_id array'      => config( array( palette( 'default' ) ), 'default', array( 'x' ) ),
	'integration_id int'        => config( array( palette( 'default' ) ), 'default', 5 ),
	'default_palette int'       => config( array( palette( 'default' ) ), 0 ),
	'palettes string'           => config( 'default' ),
	'no palettes'               => config( array() ),
	'unknown default'           => config( array( palette( 'default' ) ), 'missing' ),
	'default dropped as invalid' => config( array( palette( 'default', array( 'swatches' => array( 'red' ) ) ) ) ),
);
foreach ( $wrong_roots as $label => $cfg ) {
	check( null === _eds_dps_validate_integration( $cfg ), "integration rejected: $label" );
}

// --- Palette-level rejection drops only that palette ------------------------

$bad = array(
	'non-array palette'       => 'default2',
	'id int'                  => palette( 7 ),
	'name array'              => palette( 'a', array( 'name' => array( 'x' ) ) ),
	'name only tags'          => palette( 'b', array( 'name' => '<b></b>' ) ),
	'swatches string'         => palette( 'c', array( 'swatches' => '#fff' ) ),
	'no swatches'             => palette( 'd', array( 'swatches' => array() ) ),
	'named-color swatch'      => palette( 'e', array( 'swatches' => array( 'red' ) ) ),
	'8-digit hex swatch'      => palette( 'f', array( 'swatches' => array( '#11223344' ) ) ),
	'oklch swatch'            => palette( 'g', array( 'swatches' => array( 'oklch(50% 0.1 200)' ) ) ),
	'vars string'             => palette( 'h', array( 'vars' => '--x: red' ) ),
	'no vars'                 => palette( 'i', array( 'vars' => array() ) ),
	'int var key'             => palette( 'j', array( 'vars' => array( 0 => '#fff' ) ) ),
	'var name without --'     => palette( 'k', array( 'vars' => array( 'color' => '#fff' ) ) ),
	'var name trailing newline' => palette( 'l', array( 'vars' => array( "--x\n" => '#fff' ) ) ),
	'var name injection'      => palette( 'm', array( 'vars' => array( '--x;color' => '#fff' ) ) ),
	'var name bare --'        => palette( 'n', array( 'vars' => array( '--' => '#fff' ) ) ),
	'var value array'         => palette( 'o', array( 'vars' => array( '--x' => array( '#fff' ) ) ) ),
	'var value int'           => palette( 'p', array( 'vars' => array( '--x' => 0 ) ) ),
);
foreach ( $bad as $label => $p ) {
	$i = _eds_dps_validate_integration( config( array( palette( 'default' ), $p ) ) );
	check( null !== $i && array( 'default' ) === ids( $i ), "palette dropped: $label" );
}

$i = _eds_dps_validate_integration( config( array( palette( 'default' ), palette( 'default', array( 'name' => 'Second' ) ) ) ) );
check( 1 === count( $i['palettes'] ) && 'Default' === $i['palettes'][0]['name'], 'duplicate id: first kept' );

$i = _eds_dps_validate_integration( config( array( palette( 'zeta' ), palette( 'default' ), palette( 'alpha' ), palette( 'mid' ) ) ) );
check( array( 'zeta', 'default', 'alpha', 'mid' ) === ids( $i ), 'palette order unchanged' );

$i = _eds_dps_validate_integration( config( array( palette( 'default', array( 'name' => '<b>Dark</b> Mode', 'swatches' => array( '#abc' ) ) ) ) ) );
check( 'Dark Mode' === $i['palettes'][0]['name'], 'names sanitized for display' );
check( array( '#abc' ) === $i['palettes'][0]['swatches'], '3-digit hex swatch allowed' );

// --- CSS values: allowed ----------------------------------------------------

$allowed = array(
	'#0b3d5c',
	'#abc',
	'#0b3d5c80',
	'rebeccapurple',
	'red',
	'transparent',
	'currentColor',
	'currentcolor',
	'rgb(11 61 92)',
	'rgb(11, 61, 92)',
	'rgba(11, 61, 92, 0.5)',
	'rgb(11 61 92 / 50%)',
	'hsl(200deg 80% 20%)',
	'hsla(200, 80%, 20%, .5)',
	'hwb(200 10% 20%)',
	'lab(50% 40 59.5 / .5)',
	'lch(52% 72 56)',
	'oklab(0.6 -0.1 0.1)',
	'oklch(62% 0.18 250)',
	'color(display-p3 1 0.5 0)',
	'color-mix(in srgb, #fff 20%, #000)',
	'color-mix(in oklch, var(--a), transparent 40%)',
	'var(--other)',
	'var(--other, #fff)',
	'calc(var(--x) * 2)',
	'calc((1 + 2) * 3%)',
	'min(10%, 20%)',
	'max(1, 2)',
	'clamp(0.1, var(--a), 0.9)',
	'RGB(1 2 3)',
	'oklch(from var(--brand) calc(l * 0.9) c h)',
	'rgb(from #0b3d5c r g b / 50%)',
	'hsl(from red calc(h + 30) s l)',
	'#' . str_repeat( 'a', 199 ),
);
foreach ( $allowed as $value ) {
	check( value_ok( $value ), 'value allowed: ' . ( strlen( $value ) > 60 ? strlen( $value ) . ' chars' : $value ) );
}

// --- CSS values: rejected ---------------------------------------------------

$rejected = array(
	'url(//evil.test/a.png)',
	'URL(//evil.test/a.png)',
	'url (//evil.test/a.png)',
	'src(//evil.test/a.png)',
	'image(red)',
	'image-set(x 1x)',
	'-webkit-image-set(x 1x)',
	'cross-fade(red, blue)',
	'linear-gradient(red, blue)',
	'radial-gradient(red, blue)',
	'conic-gradient(red, blue)',
	'repeating-linear-gradient(red, blue)',
	'-moz-element(#hero)',
	'expression(alert(1))',
	'attr(data-x)',
	'env(safe-area-inset-top)',
	'red; background: blue',
	'red}body{color:red',
	'{',
	'</style><script>',
	'red > blue',
	'"red"',
	"'red'",
	'\\75rl(x)',
	'red /* x */',
	'red /* x',
	'red */',
	'red !important',
	'a:b',
	'@import',
	'rgb(1 2 3',
	'rgb(1 2 3))',
	')(',
	'',
	'   ',
	'#' . str_repeat( 'a', 200 ),
);
foreach ( $rejected as $value ) {
	check( ! value_ok( $value ), 'value rejected: ' . ( strlen( $value ) > 60 ? strlen( $value ) . ' chars' : ( '' === trim( $value ) ? '(blank)' : $value ) ) );
}

echo "All $checks contract checks passed.\n";
