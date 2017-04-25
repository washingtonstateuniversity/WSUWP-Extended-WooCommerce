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
	add_action( 'woocommerce_admin_status_content_status', '\WSU\WooCommerce_Extended\override_status_tab' );
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

/**
 * Overrides the System Settings -> Status tab to display a limited set of information.
 * The default provided by WooCommerce can be server intensive.
 *
 * @since 0.1.0
 */
function override_status_tab() {
	$system_status  = new \WC_REST_System_Status_Controller;
	$theme          = $system_status->get_theme_info();
	?>
	<table class="wc_status_table widefat" cellspacing="0">
		<thead>
		<tr>
			<th colspan="3" data-export-label="Templates"><h2><?php _e( 'Templates', 'woocommerce' ); ?><?php echo wc_help_tip( __( 'This section shows any files that are overriding the default WooCommerce template pages.', 'woocommerce' ) ); ?></h2></th>
		</tr>
		</thead>
		<tbody>
		<?php if ( $theme['has_woocommerce_file'] ) : ?>
			<tr>
				<td data-export-label="Archive Template"><?php _e( 'Archive template', 'woocommerce' ); ?>:</td>
				<td class="help">&nbsp;</td>
				<td><?php _e( 'Your theme has a woocommerce.php file, you will not be able to override the woocommerce/archive-product.php custom template since woocommerce.php has priority over archive-product.php. This is intended to prevent display issues.', 'woocommerce' ); ?></td>
			</tr>
		<?php endif ?>
		<?php
		if ( ! empty( $theme['overrides'] ) ) { ?>
			<tr>
				<td data-export-label="Overrides"><?php _e( 'Overrides', 'woocommerce' ); ?></td>
				<td class="help">&nbsp;</td>
				<td>
					<?php
					$total_overrides = count( $theme['overrides'] );
					for ( $i = 0; $i < $total_overrides; $i++ ) {
						$override = $theme['overrides'][ $i ];
						if ( $override['core_version'] && ( empty( $override['version'] ) || version_compare( $override['version'], $override['core_version'], '<' ) ) ) {
							$current_version = $override['version'] ? $override['version'] : '-';
							printf(
								__( '%1$s version %2$s is out of date. The core version is %3$s', 'woocommerce' ),
								'<code>' . $override['file'] . '</code>',
								'<strong style="color:red">' . $current_version . '</strong>',
								$override['core_version']
							);
						} else {
							echo esc_html( $override['file'] );
						}
						if ( ( count( $theme['overrides'] ) - 1 ) !== $i ) {
							echo ', ';
						}
						echo '<br />';
					}
					?>
				</td>
			</tr>
			<?php
		}

		if ( true === $theme['has_outdated_templates'] ) {
			?>
			<tr>
				<td data-export-label="Outdated Templates"><?php _e( 'Outdated templates', 'woocommerce' ); ?>:</td>
				<td class="help">&nbsp;</td>
				<td><mark class="error"><span class="dashicons dashicons-warning"></span></mark><a href="https://docs.woocommerce.com/document/fix-outdated-templates-woocommerce/" target="_blank"><?php _e( 'Learn how to update', 'woocommerce' ) ?></a></td>
			</tr>
			<?php
		}
		?>
		</tbody>
	</table>
	<?php
}
