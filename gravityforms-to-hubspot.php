<?php
/*
 * Plugin Name: Gravity Forms to HubSpot
 * Version: 1.0
 * Plugin URI: https://vtldesign.com/
 * Description: Connects Gravity Forms to HubSpot. Requires Gravity Forms and Contact Form Builder for WordPress plugins.
 * Author: Vital
 * Author URI: https://vtldesign.com/
 * Requires at least: 4.0
 * Tested up to: 5.0.3
 * Text Domain: gravityforms-to-hubspot
 * Domain Path: /lang/
 */

if (!defined('ABSPATH')) {
	exit;
}

define( 'GF2HS_VERSION', '1.0' );

add_action( 'gform_loaded', array( 'Gravity_Forms_To_HubSpot', 'load' ), 5 );

class Gravity_Forms_To_HubSpot {

	public static function load() {

		if (!method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		require_once( 'includes/helpers.php' );
		require_once( 'class-gravityforms-to-hubspot.php' );

		GFAddOn::register( 'GF2HS' );
	}

}
