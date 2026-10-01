<?php
/**
 * Removes the only option this plugin owns. Theme settings and palette definitions are untouched.
 *
 * @package eds-demo-palette-switcher
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'eds_dps_enabled' );
