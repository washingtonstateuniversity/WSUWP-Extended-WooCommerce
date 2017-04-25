<?php
/*
Plugin Name: WSUWP Extended WooCommerce
Version: 0.1.0
Description: A WordPress plugin to apply modifications to WooCommerce defaults.
Author: washingtonstateuniversity, jeremyfelt
Author URI: https://web.wsu.edu/
Plugin URI: https://github.com/washingtonstateuniversity/WSUWP-Extended-WooCommerce
*/

namespace WSU\WooCommerce_Extended;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'after_setup_theme', '\WSU\WooCommerce_Extended\bootstrap' );
/**
 * Adds hooks.
 *
 * @since 0.1.0
 */
function bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	add_filter( 'wsuwp_embeds_enable_facebook_post', '__return_false' );
	add_action( 'init', '\WSU\WooCommerce_Extended\remove_shortcode_ui', 3 );
	add_action( 'init', '\WSU\WooCommerce_Extended\remove_switch_blog_action' );
}

/**
 * Effectively disables the Shortcode UI problem due to a conflict in the Select2
 * version used by that plugin and WooCommerce.
 *
 * @since 0.0.2
 */
function remove_shortcode_ui() {
	remove_action( 'init', 'shortcode_ui_init', 5 );
	remove_action( 'init', 'Image_Shortcake::get_instance' );
}

/**
 * Removes the `$wpdb` processing that WooCommerce does when `switch_to_blog()`
 * fires. In WSUWP's configuration, `switch_to_blog()` can fire hundreds of times
 * on a page view and does not expect anything heavy to be attached to it.
 *
 * We may want to consider relying less on `switch_to_blog()` to build the menu,
 * but this will work for the time being.
 *
 * @since 0.0.1
 */
function remove_switch_blog_action() {
	remove_action( 'switch_blog', array( \WooCommerce::instance(), 'wpdb_table_fix' ), 0 );
}
