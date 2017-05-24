<?php

namespace WSU\WooCommerce_Extended\Sales_Tax;

/**
 * Short circuit database tax table lookup.
 *
 * @since 0.1.0
 */
add_filter( 'woocommerce_customer_taxable_address', '__return_empty_array', 10 );

add_filter( 'woocommerce_matched_rates', 'WSU\WooCommerce_Extended\Sales_Tax\find_tax_rate' );
add_filter( 'woocommerce_rate_code', 'WSU\WooCommerce_Extended\Sales_Tax\rate_code', 10, 2 );
add_filter( 'woocommerce_rate_label', 'WSU\WooCommerce_Extended\Sales_Tax\rate_label', 10, 2 );

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

	// If no tax rate is found using the API, use a generic 7.8%
	if ( empty( $matched_rates ) ) {
		$matched_rates[ $lookup_key ] = array(
			'rate' => 7.8,
			'label' => '7.8% Sales Tax (est)',
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
	$existing = wp_cache_get( $key, 'wsuwp_woo_tax' );

	if ( $existing && isset( $existing['matched_rates'] ) ) {
		return $existing['matched_rates'][ $key ]['label'];
	}

	return $rate_name;
}
