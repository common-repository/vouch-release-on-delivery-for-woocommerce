<?php

/**
 * Plugin Name: Vouch - Release on delivery for WooCommerce
 * Description: Allows you to use Vouch - Release on delivery payment gateway with the WooCommerce plugin.
 * Version: 1.0.0
 * Tested up to: 5.8.1
 * Stable tag: 1.0.1
 * Author Name: Vouch
 * Author URI: https://iamvouched.com
*/ 

/**
 * Class WC_Gateway_Vouch file.
 *
 * @package WooCommerce\Vouch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_action( 'plugins_loaded', 'vouch_woocommerce_payment_init', 11 );

function vouch_woocommerce_payment_init() {
    if( class_exists( 'WC_Payment_Gateway' ) ) {
		require_once plugin_dir_path(__FILE__) . '/includes/vouch-payment-option.php';
        require_once plugin_dir_path( __FILE__ ) . '/includes/vouch-checkout-description-fields.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/vouch-request-url.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/vouch-woo-callback-notify.php';
	}
}

add_filter( 'woocommerce_payment_gateways', 'add_to_woo_vouch_payment_gateway');

function add_to_woo_vouch_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Vouch';
    return $gateways;
}


// if (!function_exists('write_log')) {

//     function write_log($log) {
//         if (true === WP_DEBUG) {
//             if (is_array($log) || is_object($log)) {
//                 error_log(print_r($log, true));
//             } else {
//                 error_log($log);
//             }
//         }
//     }

// }