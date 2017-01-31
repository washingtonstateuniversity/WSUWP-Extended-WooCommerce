<?php

class WSUWP_Extended_WooCommerce {
	/**
	 * @var WSUWP_Extended_WooCommerce
	 */
	private static $instance;

	/**
	 * Maintain and return the one instance. Initiate hooks when
	 * called the first time.
	 *
	 * @since 0.0.1
	 *
	 * @return \WSUWP_Extended_WooCommerce
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WSUWP_Extended_WooCommerce();
			self::$instance->setup_hooks();
		}
		return self::$instance;
	}

	/**
	 * Setup hooks to include.
	 *
	 * @since 0.0.1
	 */
	public function setup_hooks() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_filter( 'wsuwp_embeds_enable_facebook_post', '__return_false' );
		add_action( 'init', array( $this, 'remove_shortcode_ui' ), 3 );
		add_action( 'init', array( $this, 'remove_switch_blog_action' ) );
	}

	/**
	 * Effectively disables the Shortcode UI problem due to a conflict in the Select2
	 * version used by that plugin and WooCommerce.
	 *
	 * @since 0.0.2
	 */
	public function remove_shortcode_ui() {
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
	public function remove_switch_blog_action() {
		remove_action( 'switch_blog', array( WooCommerce::instance(), 'wpdb_table_fix' ), 0 );
	}
}
