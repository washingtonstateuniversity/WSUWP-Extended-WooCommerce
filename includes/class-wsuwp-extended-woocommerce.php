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
		add_action( 'init', array( $this, 'remove_switch_blog_action' ) );
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
