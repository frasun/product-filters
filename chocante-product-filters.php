<?php
/**
 * Plugin Name: Product Filters
 * Description: Filter product listings.
 * Version: 1.0.2
 * Author: Chocante
 * Text Domain: chocante-product-filters
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Chocante_Product_Filters
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MAIN_PLUGIN_FILE' ) ) {
	define( 'MAIN_PLUGIN_FILE', __FILE__ );
}

/**
 * Current plugin version.
 */
define( 'CHOCANTE_PRODUCT_FILTERS_VERSION', '1.0.2' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-chocante-product-filters.php';
add_action( 'plugins_loaded', 'chocante_product_filters_init', 10 );

/**
 * Load text domain
 */
function chocante_product_filters_init() {
	load_plugin_textdomain( 'chocante-product-filters', false, plugin_basename( __DIR__ ) . '/languages' );

	Chocante_Product_Filters::instance();
}

register_activation_hook( __FILE__, 'chocante_product_filters_activate' );

/**
 * Activation hook
 */
function chocante_product_filters_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'chocante_product_filters_missing_wc_notice' );
		return;
	}
}

/**
 * WooCommerce fallback notice
 */
function chocante_product_filters_missing_wc_notice() {
	/* translators: %s WC download URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Product Fitlers requires WooCommerce to be installed and active. You can download %s here.', 'chocante-product-filters' ), '<a href="https://woo.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * Public function to output filters
 */
function chocante_product_filters() {
	Chocante_Product_Filters::instance()->display_filters();
}

/**
 * Public function to check if any filter is set
 */
function chocante_has_product_filters() {
	return Chocante_Product_Filters::instance()->has_filters();
}
