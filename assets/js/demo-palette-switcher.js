/**
 * EDS Demo Palette Switcher controller.
 *
 * Reads window.edsDemoPalette (printed by the head bootstrap), applies the
 * chosen palette via html[data-eds-demo-palette], and persists only the
 * palette ID in this visitor's localStorage. No reloads, no requests.
 *
 * UI: a "Try Colors" toggle discloses a non-modal panel. Escape, the close
 * button, the toggle or a click outside collapses it; focus returns to the
 * toggle when it was inside the switcher.
 */
( function () {
	var ATTR = 'data-eds-demo-palette';
	var config = window.edsDemoPalette;
	var widget = document.getElementById( 'eds-dps' );
	if ( ! config || ! widget ) {
		return;
	}

	var root = document.documentElement;
	var toggle = widget.querySelector( '[data-eds-dps-toggle]' );
	var panel = widget.querySelector( '[data-eds-dps-panel]' );
	var close = widget.querySelector( '[data-eds-dps-close]' );
	var buttons = widget.querySelectorAll( '[data-eds-dps-palette]' );
	var current = widget.querySelector( '[data-eds-dps-current]' );
	var reset = widget.querySelector( '[data-eds-dps-reset]' );

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

	function open() {
		panel.hidden = false;
		toggle.setAttribute( 'aria-expanded', 'true' );
		// Start on the active palette so a long list opens where the visitor is.
		for ( var i = 0; i < buttons.length; i++ ) {
			if ( 'true' === buttons[ i ].getAttribute( 'aria-pressed' ) ) {
				buttons[ i ].focus( { preventScroll: true } );
				if ( buttons[ i ].scrollIntoView ) {
					buttons[ i ].scrollIntoView( { block: 'nearest' } );
				}
				break;
			}
		}
	}

	function collapse( returnFocus ) {
		if ( panel.hidden ) {
			return;
		}
		panel.hidden = true;
		toggle.setAttribute( 'aria-expanded', 'false' );
		if ( returnFocus ) {
			toggle.focus();
		}
	}

	for ( var i = 0; i < buttons.length; i++ ) {
		buttons[ i ].addEventListener( 'click', function () {
			select( this.getAttribute( 'data-eds-dps-palette' ) );
		} );
	}
	reset.addEventListener( 'click', clear );
	toggle.addEventListener( 'click', function () {
		if ( panel.hidden ) {
			open();
		} else {
			collapse( true );
		}
	} );
	close.addEventListener( 'click', function () {
		collapse( true );
	} );
	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && ! panel.hidden ) {
			collapse( widget.contains( document.activeElement ) );
		}
	} );
	// Listen only; theme clicks keep working and are never stopped.
	document.addEventListener( 'click', function ( event ) {
		if ( ! panel.hidden && ! widget.contains( event.target ) ) {
			collapse( false );
		}
	} );

	sync();
	widget.hidden = false;
}() );
