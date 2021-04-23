<?php
/*
 * Plugin Name: Gravity Forms to HubSpot
 * Version: 1.3
 * Plugin URI: https://vtldesign.com/
 * Description: Connects Gravity Forms to HubSpot. Requires Gravity Forms.
 * Author: Vital
 * Author URI: https://vtldesign.com/
 * Requires at least: 4.0
 * Tested up to: 5.6
 * Text Domain: gravityforms-to-hubspot
 * Domain Path: /lang/
 */

if (!defined('ABSPATH')) {
	exit;
}

define( 'GF2HS_VERSION', '1.1' );

add_action( 'gform_loaded', array( 'Gravity_Forms_To_HubSpot', 'load' ), 5 );

class Gravity_Forms_To_HubSpot {

	public static function load() {

		if (!method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		if (!function_exists('isset_and_true')) {
			$class = 'notice notice-error';
			$message = __( 'Hubspot to Gravityforms is missing required theme function: isset_and_true.');

			if (is_admin()) {
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
			}
			return;
		}

		require_once( 'includes/helpers.php' );
		require_once( 'class-gravityforms-to-hubspot.php' );

		GFAddOn::register( 'GF2HS' );
	}

}
