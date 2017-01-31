<?php
/*
Plugin Name: WSUWP Extended WooCommerce
Version: 0.0.3
Description: A WordPress plugin to apply modifications to WooCommerce defaults.
Author: washingtonstateuniversity, jeremyfelt
Author URI: https://web.wsu.edu/
Plugin URI: https://github.com/washingtonstateuniversity/WSUWP-Extended-WooCommerce
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// The core plugin class.
require dirname( __FILE__ ) . '/includes/class-wsuwp-extended-woocommerce.php';

add_action( 'after_setup_theme', 'WSUWP_Extended_WooCommerce' );
/**
 * Start things up.
 *
 * @return \WSUWP_Extended_WooCommerce
 */
function WSUWP_Extended_WooCommerce() {
	return WSUWP_Extended_WooCommerce::get_instance();
}
