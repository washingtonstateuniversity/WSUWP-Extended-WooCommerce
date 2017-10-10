<?php

namespace WSU\WooCommerce_Extended\Sales_Tax;

add_action( 'init', 'WSU\WooCommerce_Extended\Sales_Tax\register_post_type' );
add_filter( 'woocommerce_find_rates', 'WSU\WooCommerce_Extended\Sales_Tax\find_tax_rate' );
add_filter( 'woocommerce_rate_code', 'WSU\WooCommerce_Extended\Sales_Tax\rate_code', 10, 2 );
add_filter( 'woocommerce_rate_label', 'WSU\WooCommerce_Extended\Sales_Tax\rate_label', 10, 2 );

/**
 * Show only one tax line item to the customer rather than the same tax rate
 * multiple times for each product and shipping.
 *
 * @since 0.1.1
 */
add_filter( 'pre_option_woocommerce_tax_total_display', '__return_zero' );

/**
 * Maintain a common cache key for tax lookups.
 *
 * @since 0.2.0
 *
 * @return string
 */
function get_cache_group() {
	return 'wsuwpwootax_01';
}

/**
 * Return the slug used for the tax rate post type.
 *
 * @since 0.2.0
 *
 * @return string
 */
function get_post_type_slug() {
	return 'wsu_tax_rate';
}

/**
 * Register a post type to store tax rate look-ups for repeated use.
 *
 * @since 0.2.0
 */
function register_post_type() {
	$labels = array(
		'name' => 'Tax Rates',
		'singular_name' => 'Tax Rate',
		'add_new' => 'Add New',
	);

	$args = array(
		'labels'             => $labels,
		'public'             => false,
		'publicly_queryable' => false,
		'show_ui'            => true,
		'show_in_menu'       => false,
		'query_var'          => false,
		'rewrite'            => false,
		'has_archive'        => false,
		'hierarchical'       => false,
		'supports'           => array( 'title' ),
	);
	\register_post_type( get_post_type_slug(), $args );
}

/**
 * Build the data necessary for making a request to the WA tax API.
 *
 * @since 0.2.0
 *
 * @param \WC_Order $order
 *
 * @return array
 */
function get_tax_request_data( $order = 0 ) {
	if ( is_admin() && isset( $_POST['order_id'] ) && 0 === $order ) { // @codingStandardsIgnoreLine
		$order = absint( $_POST['order_id'] );
	}

	if ( ! WC()->customer && 0 === $order ) {
		return array();
	}

	if ( 0 !== $order ) {
		$order = wc_get_order( $order );
		$address = $order->get_shipping_address_1();
		$state = $order->get_shipping_state();
		$postcode = $order->get_shipping_postcode();
		$city = $order->get_shipping_city();
	} elseif ( WC()->customer && ! is_admin() ) {
		$address = WC()->customer->get_shipping_address_1();
		$state    = WC()->customer->get_shipping_state();
		$postcode = WC()->customer->get_shipping_postcode();
		$city     = WC()->customer->get_shipping_city();
	} else {
		return array();
	}

	// Only handle taxes for the state of Washington.
	if ( 'WA' !== $state ) {
		return array();
	}

	// When first building the cart, all fields are empty.
	if ( empty( $address ) && empty( $postcode ) && empty( $city ) ) {
		return array();
	}

	$tax_url = 'https://webgis.dor.wa.gov/webapi/addressrates.aspx';
	$tax_url = add_query_arg( array(
		'output' => 'text',
		'addr' => rawurlencode( $address ),
		'city' => rawurlencode( $city ),
		'zip' => rawurlencode( $postcode ),
	), $tax_url );

	$lookup_key = md5( $tax_url );

	return array(
		'rate_key' => $lookup_key,
		'request_url' => $tax_url,
	);
}

/**
 * Store the API data associated with an address/rate lookup.
 *
 * @since 0.2.0
 *
 * @param string $rate_key
 * @param array  $rate_data
 *
 * @return int|\WP_Error
 */
function store_rate_data( $rate_key, $rate_data = array() ) {
	$rate = get_page_by_title( $rate_key, OBJECT, get_post_type_slug() );
	$rate = $rate->ID;

	if ( ! $rate ) {
		$rate = wp_insert_post( array(
			'post_type' => get_post_type_slug(),
			'post_title' => $rate_key,
		) );
	}

	if ( ! is_wp_error( $rate ) && ! empty( $rate_data ) ) {
		update_post_meta( $rate, '_wsu_woocommerce_rate_data', $rate_data );
	}

	return $rate;
}

/**
 * Get the stored rate object for an API lookup key.
 *
 * @since 0.2.0
 *
 * @param string $rate_key
 *
 * @return null|\WP_Post
 */
function get_stored_rate( $rate_key ) {
	$rate = get_page_by_title( $rate_key, OBJECT, get_post_type_slug() );

	return $rate;
}

/**
 * Get the data attached to a stored rate for an API lookup key.
 *
 * @since 0.2.0
 *
 * @param string $rate_key
 *
 * @return array
 */
function get_stored_rate_data( $rate_key ) {
	$rate = get_stored_rate( $rate_key );

	if ( ! $rate ) {
		return array();
	}

	$rate_data = get_post_meta( $rate->ID, '_wsu_woocommerce_rate_data', true );

	if ( ! $rate_data ) {
		return array();
	}

	return $rate_data;
}

/**
 * Finds the tax rate for a customer's cart address using the
 * WA DOR sales tax API.
 *
 * @since 0.1.0
 *
 * @return array
 */
function find_tax_rate( $order_id = 0 ) {
	if ( is_array( $order_id ) ) {
		$order_id = 0;
	}

	$request_data = get_tax_request_data( $order_id );

	if ( empty( $request_data ) ) {
		return array();
	}

	$existing = wp_cache_get( $request_data['rate_key'], get_cache_group() );

	if ( $existing ) {
		return $existing;
	}

	$matched_rate_data = get_stored_rate_data( $request_data['rate_key'] );

	if ( ! empty( $matched_rate_data ) ) {
		wp_cache_set( $request_data['rate_key'], $matched_rate_data, get_cache_group() );

		return $matched_rate_data;
	}

	$response = wp_remote_get( $request_data['request_url'], array(
		'sslverify' => false,
	) );

	$rate_id = store_rate_data( $request_data['rate_key'] );
	$matched_rates = array();

	if ( ! is_wp_error( $response ) ) {
		$result = trim( wp_remote_retrieve_body( $response ) );
		$result = explode( ' ', $result );

		foreach ( $result as $res ) {
			if ( 0 === strpos( $res, 'Rate=' ) ) {
				$rate = substr( $res, 5 );

				// If the address is invalid, the API will return -1 for a rate.
				if ( '-1' === $rate ) {
					continue;
				}

				$matched_rates[ $rate_id ] = array(
					'rate' => $rate * 100,
					'label' => ( $rate * 100 ) . '% Sales Tax',
					'shipping' => 'yes',
					'compound' => 'no',
				);
			}
		}
	} else {
		$logger = new \WC_Logger();
		$logger->add( 'wsuws-tax', 'Tax lookup failed: ' . $response->get_error_message() . ', ' . $request_data['request_url'] );
	}

	$exp = 0;

	// If no tax rate is found using the API, use the highest WA state rate - 10.4%.
	if ( empty( $matched_rates ) ) {
		$matched_rates[ $rate_id ] = array(
			'rate' => 10.4,
			'label' => '10.4% (estimated) Sales Tax',
			'shipping' => 'yes',
			'compound' => 'no',
		);

		// Estimated tax rates should not live in cache long.
		$exp = 60;
	} else {

		// If a tax rate was found, store it persistently before caching it.
		store_rate_data( $request_data['rate_key'], $matched_rates );
	}

	wp_cache_set( $request_data['rate_key'], $matched_rates, get_cache_group(), $exp );

	return $matched_rates;
}

/**
 * Provides the tax code (COUNTRY-STATE-TAX-PRIORITY) expected by
 * WooCommerce for a tax rate.
 *
 * @since 0.1.0
 *
 * @param string $code_string
 * @param string $key
 *
 * @return string
 */
function rate_code( $code_string, $key ) {
	if ( 'shipping' === $key ) {
		return 'shipping';
	}

	return 'US-WA-TAX-1';
}

/**
 * Provides the label displayed to the customer for a tax rate.
 *
 * @since 0.1.0
 *
 * @param string $rate_name
 * @param string $key
 *
 * @return string
 */
function rate_label( $rate_name, $key ) {
	if ( 'shipping' === $key ) {
		$rates = find_tax_rate();
		foreach ( $rates as $key => $rate ) {
			return $rates[ $key ]['label'] . ' (Shipping)';
		}
	}

	$rate = find_tax_rate();
	$rate = reset( $rate );

	return $rate['label'];
}
