<?php

namespace WSU\WooCommerce_Extended\Sales_Tax;

add_filter( 'woocommerce_find_rates', 'WSU\WooCommerce_Extended\Sales_Tax\find_tax_rate' );
add_filter( 'woocommerce_rate_code', 'WSU\WooCommerce_Extended\Sales_Tax\rate_code', 10, 2 );
add_filter( 'woocommerce_rate_label', 'WSU\WooCommerce_Extended\Sales_Tax\rate_label', 10, 2 );
add_filter( 'woocommerce_calc_shipping_tax', 'WSU\WooCommerce_Extended\Sales_Tax\calculate_shipping_tax', 10, 3 );

/**
 * Show only one tax line item to the customer rather than the same tax rate
 * multiple times for each product and shipping.
 *
 * @since 0.1.1
 */
add_filter( 'pre_option_woocommerce_tax_total_display', '__return_zero' );

/**
 * Finds the tax rate for a customer's cart address using the
 * WA DOR sales tax API.
 *
 * @since 0.1.0
 *
 * @return array
 */
function find_tax_rate() {
	if ( ! WC()->customer ) {
		return array();
	}

	$address = WC()->customer->get_shipping_address_1();
	$state    = WC()->customer->get_shipping_state();
	$postcode = WC()->customer->get_shipping_postcode();
	$city     = WC()->customer->get_shipping_city();

	if ( 'WA' !== $state ) {
		return array();
	}

	$matched_rates = array();

	$tax_url = 'http://dor.wa.gov/AddressRates.aspx';
	$tax_url = add_query_arg( array(
		'output' => 'text',
		'addr' => urlencode( $address ),
		'city' => urlencode( $city ),
		'zip' => urlencode( $postcode ),
	), $tax_url );

	$lookup_key = md5( $tax_url );

	$existing = wp_cache_get( $lookup_key, 'wsuwp_woo_tax' );

	if ( $existing && isset( $existing['matched_rates'] ) ) {
		return $existing['matched_rates'];
	}

	$response = wp_remote_get( $tax_url );

	if ( ! is_wp_error( $response ) ) {
		$result = trim( wp_remote_retrieve_body( $response ) );
		$result = explode( ' ', $result );

		foreach( $result as $res ) {
			if ( 0 === strpos( $res, 'Rate=' ) ) {
				$rate = substr( $res, 5 );

				// If the address is invalid, the API will return -1 for a rate.
				if ( '-1' === $rate ) {
					continue;
				}

				$matched_rates[ $lookup_key ] = array(
					'rate' => $rate * 100,
					'label' => ( $rate * 100 ) . '% Sales Tax',
					'shipping' => 'yes',
					'compound' => 'no',
				);
			}
		}
	}

	$exp = 0;

	// If no tax rate is found using the API, use the highest WA state rate - 10.4%.
	if ( empty( $matched_rates ) ) {
		$matched_rates[ $lookup_key ] = array(
			'rate' => 10.4,
			'label' => '10.4% (estimated) Sales Tax',
			'shipping' => 'yes',
			'compound' => 'no',
		);

		// Estimated tax rates should not live in cache long.
		$exp = 60;
	}

	$code_storage = array(
		'location_code' => 'US-WA-TAX-1',
		'matched_rates' => $matched_rates,
	);
	wp_cache_set( $lookup_key, $code_storage, 'wsuwp_woo_tax', $exp );

	return $code_storage['matched_rates'];
}

/**
 * Calculates the sales tax on shipping costs.
 *
 * @since 0.1.1
 *
 * @param $taxes
 * @param $price
 * @param $rates
 *
 * @return array
 */
function calculate_shipping_tax( $taxes, $price, $rates ) {
	$rate = find_tax_rate();

	if ( empty( $rate ) ) {
		return array( 'shipping' => 0 );
	}

	$rate = reset( $rate );

	return array( 'shipping' => $price * ( $rate['rate'] / 100 ) );
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

	$existing = wp_cache_get( $key, 'wsuwp_woo_tax' );

	if ( $existing && isset( $existing['location_code'] ) ) {
		return $existing['location_code'];
	}

	return $code_string;
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
		foreach( $rates as $key => $rate ) {
			return $rates[ $key ]['label'] . " (Shipping)";
		}
	}

	$existing = wp_cache_get( $key, 'wsuwp_woo_tax' );

	if ( $existing && isset( $existing['matched_rates'] ) ) {
		return $existing['matched_rates'][ $key ]['label'];
	}

	return $rate_name;
}
