/**
 * EDS Demo Palette Switcher controller.
 *
 * Reads window.edsDemoPalette (printed by the head bootstrap), applies the
 * chosen palette via html[data-eds-demo-palette], and persists only the
 * palette ID in this visitor's localStorage. No reloads, no requests.
 */
( function () {
	var ATTR = 'data-eds-demo-palette';
	var config = window.edsDemoPalette;
	var panel = document.getElementById( 'eds-dps' );
	if ( ! config || ! panel ) {
		return;
	}

	var root = document.documentElement;
	var buttons = panel.querySelectorAll( '[data-eds-dps-palette]' );
	var current = panel.querySelector( '[data-eds-dps-current]' );
	var reset = panel.querySelector( '[data-eds-dps-reset]' );

	function isPalette( id ) {
		return Object.prototype.hasOwnProperty.call( config.palettes, id );
	}

	function sync() {
		var override = root.getAttribute( ATTR );
		var active = override && isPalette( override ) ? override : config.defaultPalette;
		for ( var i = 0; i < buttons.length; i++ ) {
			buttons[ i ].setAttribute( 'aria-pressed', String( buttons[ i ].getAttribute( 'data-eds-dps-palette' ) === active ) );
		}
		current.textContent = config.palettes[ active ] + ( override ? '' : ' (site default)' );
	}

	function select( id ) {
		if ( ! isPalette( id ) ) {
			return;
		}
		root.setAttribute( ATTR, id );
		try {
			window.localStorage.setItem( config.key, id );
		} catch ( e ) {} // Storage unavailable: the preview still applies for this page.
		sync();
	}

	function clear() {
		try {
			window.localStorage.removeItem( config.key );
		} catch ( e ) {}
		root.removeAttribute( ATTR );
		sync();
	}

	for ( var i = 0; i < buttons.length; i++ ) {
		buttons[ i ].addEventListener( 'click', function () {
			select( this.getAttribute( 'data-eds-dps-palette' ) );
		} );
	}
	reset.addEventListener( 'click', clear );

	sync();
	panel.hidden = false;
}() );
