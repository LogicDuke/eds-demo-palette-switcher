<?php
/**
 * Phase 2 check: setting, eligibility, frontend output, and (via Node) the
 * bootstrap + controller behavior. Run: php tests/frontend-check.php
 * Exits non-zero on the first failure. Node is required for the browser-logic checks.
 */

define( 'ABSPATH', __DIR__ );

$options = array();
function add_action() {}
function get_option( $name, $default = false ) {
	global $options;
	return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}
function sanitize_text_field( $str ) {
	return trim( strip_tags( $str ) );
}
function sanitize_hex_color( $color ) {
	return preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ? $color : null;
}
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}
function esc_attr( $s ) {
	return htmlspecialchars( $s, ENT_QUOTES );
}
function esc_html( $s ) {
	return htmlspecialchars( $s, ENT_QUOTES );
}
function esc_attr_e( $s ) {
	echo esc_attr( $s );
}
function esc_html_e( $s ) {
	echo esc_html( $s );
}
$customize_preview = false;
function is_customize_preview() {
	global $customize_preview;
	return $customize_preview;
}
function wp_print_inline_script_tag( $js, $attrs ) {
	echo '<script id="' . $attrs['id'] . '">' . $js . "</script>\n";
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

function output( $callback ) {
	ob_start();
	$callback();
	return ob_get_clean();
}

function all_output() {
	return output( '_eds_dps_print_bootstrap' ) . output( '_eds_dps_print_palette_css' ) . output( '_eds_dps_print_panel' );
}

// --- Setting ----------------------------------------------------------------

check( false === _eds_dps_is_enabled(), 'setting defaults to off' );
foreach ( array( '1', 1, true ) as $on ) {
	check( '1' === _eds_dps_sanitize_enabled( $on ), 'sanitize on: ' . var_export( $on, true ) );
}
foreach ( array( null, '', '0', 0, false, 'yes', 'on', '1 ', array( '1' ) ) as $off ) {
	check( '0' === _eds_dps_sanitize_enabled( $off ), 'sanitize off: ' . str_replace( "\n", '', var_export( $off, true ) ) );
}
$options['eds_dps_enabled'] = '0';
check( false === _eds_dps_is_enabled(), "'0' is off" );

// --- Eligibility ------------------------------------------------------------

$options['eds_dps_enabled'] = '1';
check( null === _eds_dps_frontend_integration(), 'enabled but nothing registered => inert' );
check( '' === all_output(), 'enabled but nothing registered => no output' );

eds_dps_register_theme_palettes( array( 'integration_id' => 'broken' ) );
check( null === _eds_dps_frontend_integration(), 'enabled with invalid registration => inert' );

$registered = eds_dps_register_theme_palettes(
	array(
		'integration_id'  => 'example-theme',
		'default_palette' => 'light',
		'palettes'        => array(
			array(
				'id'       => 'light',
				'name'     => 'Light',
				'swatches' => array( '#ffffff', '#111111' ),
				'vars'     => array( '--example-bg' => '#ffffff', '--example-text' => '#111111' ),
			),
			array(
				'id'       => 'dark',
				'name'     => 'Tom & "Jerry\'s" Night',
				'swatches' => array( '#000' ),
				'vars'     => array( '--example-bg' => 'oklch(20% 0.02 250)', '--example-text' => 'color-mix(in srgb, #fff 90%, transparent)' ),
			),
		),
	)
);
check( $registered, 'test integration registers' );

$options['eds_dps_enabled'] = '0';
check( null === _eds_dps_frontend_integration(), 'registered but disabled => inert' );
check( '' === all_output(), 'registered but disabled => no output' );

$options['eds_dps_enabled'] = '1';
$integration = _eds_dps_frontend_integration();
check( null !== $integration && 'example-theme' === $integration['integration_id'], 'enabled + valid registration => active' );

$customize_preview = true;
check( null === _eds_dps_frontend_integration(), 'Customizer preview => inert' );
check( '' === all_output(), 'Customizer preview => no output' );
$customize_preview = false;
check( null !== _eds_dps_frontend_integration(), 'outside the Customizer preview => active again' );

// --- Payload ----------------------------------------------------------------

check( 'eds-dps:example-theme' === _eds_dps_storage_key( 'example-theme' ), 'storage key format' );

$config = _eds_dps_client_config( $integration );
check( 'eds-dps:example-theme' === $config['key'] && 'light' === $config['defaultPalette'], 'client config key + default' );
check( '{"0":"Zero"}' === json_encode( _eds_dps_client_config( array( 'integration_id' => 'x', 'default_palette' => '0', 'palettes' => array( array( 'id' => '0', 'name' => 'Zero' ) ) ) )['palettes'] ), 'numeric palette ids stay a JSON object' );

$bootstrap = _eds_dps_bootstrap_script( $integration );
$hex_escaped = json_encode( 'Tom & "Jerry\'s" Night', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
check( false === strpos( $bootstrap, '"Jerry' ) && false === strpos( $bootstrap, ' & ' ) && false !== strpos( $bootstrap, $hex_escaped ), 'bootstrap JSON hex-escapes & " \'' );
check( false === strpos( $bootstrap, '<' ) && false === strpos( $bootstrap, '>' ), 'bootstrap has no < or >' );

$css = _eds_dps_palette_css( $integration );
check(
	'html[data-eds-demo-palette="light"]{--example-bg:#ffffff;--example-text:#111111;}'
	. 'html[data-eds-demo-palette="dark"]{--example-bg:oklch(20% 0.02 250);--example-text:color-mix(in srgb, #fff 90%, transparent);}' === $css,
	'palette CSS generated exactly from registered data'
);

$head = output( '_eds_dps_print_bootstrap' );
check( 0 === strpos( $head, '<script id="eds-dps-bootstrap">window.edsDemoPalette=' ), 'bootstrap printed as inline script' );
check( '<style id="eds-dps-palettes">' . $css . "</style>\n" === output( '_eds_dps_print_palette_css' ), 'palette CSS printed in style tag' );

$panel = output( '_eds_dps_print_panel' );
check( false !== strpos( $panel, 'id="eds-dps"' ) && false !== strpos( $panel, ' hidden>' ), 'panel hidden until JS runs' );
check( false !== strpos( $panel, 'class="eds-dps notranslate" translate="no"' ), 'switcher UI and palette names opt out of page translation' );
check( 2 === substr_count( $panel, 'aria-pressed="false"' ), 'one aria-pressed button per palette' );
check( 2 === substr_count( $panel, '<button type="button" class="eds-dps__palette"' ), 'palette controls are real buttons' );
check( false !== strpos( $panel, 'data-eds-dps-reset>Reset to Default</button>' ), 'reset button present' );
check( false !== strpos( $panel, 'Tom &amp; &quot;Jerry&#039;s&quot; Night' ), 'palette name HTML-escaped' );
check( false !== strpos( $panel, 'style="background-color:#000"' ), 'swatches rendered' );
check( false !== strpos( $panel, 'aria-hidden="true"' ), 'swatches hidden from screen readers' );
check( 1 === preg_match( '#<button type="button" class="eds-dps__toggle" data-eds-dps-toggle aria-expanded="false" aria-controls="eds-dps-panel">#', $panel ), 'toggle is a button with aria-expanded + aria-controls' );
check( false !== strpos( $panel, 'Try Colors' ), 'toggle labelled Try Colors' );
check( 1 === preg_match( '#<section id="eds-dps-panel"[^>]*aria-labelledby="eds-dps-title" hidden>#', $panel ) && false !== strpos( $panel, 'id="eds-dps-title"' ), 'panel collapsed by default and labelled by its title' );
check( 1 === preg_match( '#<button type="button" class="eds-dps__close" data-eds-dps-close aria-label="Close color preview">#', $panel ), 'close button has an accessible name' );
check( 1 === substr_count( $panel, 'Site default</span>' ) && 1 === preg_match( '#data-eds-dps-palette="light".*?Site default</span>.*?data-eds-dps-palette="dark"#s', $panel ), 'only the configured default is marked Site default' );
check( false !== strpos( $panel, 'aria-live="polite"' ), 'current palette announced politely' );

// --- Browser logic via Node -------------------------------------------------

$controller = file_get_contents( dirname( __DIR__ ) . '/assets/js/demo-palette-switcher.js' );
$node_script = 'var vm = require("vm"), bootstrap = ' . json_encode( $bootstrap ) . ', controller = ' . json_encode( $controller ) . ";\n" . <<<'JS'
function page(stored, mode) {
	var data = stored === null ? {} : { 'eds-dps:example-theme': stored };
	var storage = {
		getItem(k) { if (mode === 'throw') throw new Error('denied'); return k in data ? data[k] : null; },
		setItem(k, v) { if (mode === 'throw' || mode === 'full') throw new Error('denied'); data[k] = String(v); },
		removeItem(k) { if (mode === 'throw') throw new Error('denied'); delete data[k]; },
	};
	var doc = { listeners: {}, activeElement: null,
		addEventListener(t, f) { (this.listeners[t] = this.listeners[t] || []).push(f); } };
	var inside = [];
	function el(attrs, member) {
		var node = { attrs: Object.assign({}, attrs), listeners: {}, hidden: true, textContent: '', scrolled: false,
			getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; },
			setAttribute(n, v) { this.attrs[n] = String(v); },
			removeAttribute(n) { delete this.attrs[n]; },
			addEventListener(t, f) { this.listeners[t] = f; },
			focus() { doc.activeElement = this; },
			scrollIntoView() { this.scrolled = true; },
			// A real click runs the element's handler, then bubbles to document.
			click() { if (this.listeners.click) this.listeners.click.call(this); fire('click', { target: this }); } };
		if (member) inside.push(node);
		return node;
	}
	function fire(type, event) { (doc.listeners[type] || []).forEach((f) => f(event)); }
	var root = el({}), outside = el({});
	var current = el({}, 1), reset = el({}, 1), toggle = el({ 'aria-expanded': 'false' }, 1), sheet = el({}, 1), close = el({}, 1);
	var buttons = [el({ 'data-eds-dps-palette': 'light' }, 1), el({ 'data-eds-dps-palette': 'dark' }, 1)];
	var panel = el({});
	panel.contains = (node) => node === panel || inside.indexOf(node) > -1;
	panel.querySelectorAll = () => buttons;
	panel.querySelector = (s) => ({ '[data-eds-dps-current]': current, '[data-eds-dps-reset]': reset, '[data-eds-dps-toggle]': toggle,
		'[data-eds-dps-panel]': sheet, '[data-eds-dps-close]': close })[s];
	doc.documentElement = root;
	doc.getElementById = (id) => (id === 'eds-dps' ? panel : null);
	var ctx = { document: doc };
	ctx.window = ctx;
	if (mode === 'getter-throws') Object.defineProperty(ctx, 'localStorage', { get() { throw new Error('SecurityError'); } });
	else ctx.localStorage = storage;
	vm.createContext(ctx);
	var error = null;
	try { vm.runInContext(bootstrap, ctx); } catch (e) { error = e; }
	var afterBootstrap = root.getAttribute('data-eds-demo-palette');
	try { vm.runInContext(controller, ctx); } catch (e) { error = error || e; }
	return { ctx, doc, data, root, buttons, current, reset, panel, toggle, sheet, close, outside, afterBootstrap, error,
		key: (k) => fire('keydown', { key: k }),
		expanded: () => toggle.getAttribute('aria-expanded'),
		pressed: () => buttons.map((b) => b.getAttribute('aria-pressed')).join(',') };
}
var r = [], p;
function t(ok, label) { r.push([!!ok, label]); }

p = page(null);
t(p.afterBootstrap === null && !p.error, 'bootstrap: nothing stored => no attribute');
t(p.current.textContent === 'Light (site default)' && p.pressed() === 'true,false', 'controller: no override shows site default as current');
t(p.panel.hidden === false, 'controller: reveals panel');

p = page('dark');
t(p.afterBootstrap === 'dark', 'bootstrap: valid stored palette applied before controller');
t(p.pressed() === 'false,true' && p.current.textContent.indexOf('Night') > -1, 'controller: reflects stored palette');

['nope', '', 'DARK', '__proto__', 'hasOwnProperty', 'constructor', 'dark"]'].forEach(function (v) {
	p = page(v);
	t(p.afterBootstrap === null && !p.error, 'bootstrap: ignores invalid stored value ' + JSON.stringify(v));
});

p = page('dark', 'throw');
t(p.afterBootstrap === null && !p.error, 'bootstrap: storage methods throwing => no attribute, no throw');
p = page('dark', 'getter-throws');
t(p.afterBootstrap === null && !p.error, 'bootstrap: localStorage access throwing => no attribute, no throw');

p = page(null);
p.buttons[1].click();
t(p.root.getAttribute('data-eds-demo-palette') === 'dark', 'select: sets html attribute immediately');
t(p.data['eds-dps:example-theme'] === 'dark', 'select: persists only the palette id');
t(p.pressed() === 'false,true' && p.current.textContent.indexOf('site default') === -1, 'select: updates aria-pressed and current label');
p.buttons[0].click();
t(p.root.getAttribute('data-eds-demo-palette') === 'light' && p.data['eds-dps:example-theme'] === 'light', 'select default palette: stored as explicit choice');

p.reset.click();
t(p.root.getAttribute('data-eds-demo-palette') === null, 'reset: removes html attribute');
t(!('eds-dps:example-theme' in p.data), 'reset: removes stored selection');
t(p.current.textContent === 'Light (site default)' && p.pressed() === 'true,false', 'reset: returns to site default state');

p = page(null, 'full');
p.buttons[1].click();
t(p.root.getAttribute('data-eds-demo-palette') === 'dark' && !p.error, 'select: still previews when storage write fails');

p = page(null, 'throw');
p.buttons[1].click();
p.reset.click();
t(p.root.getAttribute('data-eds-demo-palette') === null && !p.error, 'reset: works when storage unavailable');

// --- Open / close ---
p = page('dark');
t(p.sheet.hidden === true && p.expanded() === 'false', 'ui: starts collapsed, aria-expanded=false');
p.toggle.click();
t(p.sheet.hidden === false && p.expanded() === 'true', 'ui: toggle opens, aria-expanded=true');
t(p.doc.activeElement === p.buttons[1] && p.buttons[1].scrolled, 'ui: open focuses and reveals the active palette');
p.buttons[0].click();
t(p.sheet.hidden === false && p.root.getAttribute('data-eds-demo-palette') === 'light' && p.pressed() === 'true,false', 'ui: selecting keeps panel open and updates selection');
p.toggle.click();
t(p.sheet.hidden === true && p.expanded() === 'false' && p.doc.activeElement === p.toggle, 'ui: toggle again collapses, focus on toggle');

p.toggle.click();
p.key('Escape');
t(p.sheet.hidden === true && p.expanded() === 'false' && p.doc.activeElement === p.toggle, 'ui: Escape collapses and returns focus to toggle');
p.key('Escape');
t(p.sheet.hidden === true && !p.error, 'ui: Escape while collapsed is a no-op');
p.key('Enter');
t(p.sheet.hidden === true, 'ui: other keys ignored');

p.toggle.click();
p.close.click();
t(p.sheet.hidden === true && p.expanded() === 'false' && p.doc.activeElement === p.toggle, 'ui: close button collapses and returns focus');

p.toggle.click();
p.outside.focus();
p.outside.click();
t(p.sheet.hidden === true && p.expanded() === 'false' && p.doc.activeElement === p.outside, 'ui: click outside collapses without stealing focus');

p.toggle.click();
p.reset.click();
t(p.sheet.hidden === false && p.root.getAttribute('data-eds-demo-palette') === null && !('eds-dps:example-theme' in p.data), 'ui: reset inside open panel keeps reset semantics');
t(p.pressed() === 'true,false' && p.current.textContent === 'Light (site default)', 'ui: reset shows site default selected');
p.toggle.click();
t(p.doc.activeElement === p.toggle && p.data['eds-dps:example-theme'] === undefined, 'ui: open/close writes nothing to storage');

process.stdout.write(JSON.stringify(r));
JS;

$process = proc_open( array( 'node', '-' ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
check( is_resource( $process ), 'node available' );
fwrite( $pipes[0], $node_script );
fclose( $pipes[0] );
$stdout = stream_get_contents( $pipes[1] );
$stderr = stream_get_contents( $pipes[2] );
proc_close( $process );
$results = json_decode( $stdout, true );
check( is_array( $results ), 'node checks ran' . ( is_array( $results ) ? '' : ": $stderr" ) );
foreach ( $results as $result ) {
	check( $result[0], $result[1] );
}

echo "All $checks frontend checks passed.\n";
