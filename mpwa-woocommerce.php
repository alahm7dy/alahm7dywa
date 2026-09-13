<?php
/**
 * Plugin Name:       Alahm7dy WooCommerce WhatsApp
 * Plugin URI:        https://alahm7dy.com
 * Description:       إرسال إشعارات طلبات WooCommerce عبر واتساب وتأكيد الطلبات للوطن العربي.
 * Version:           3.2.0
 * Author:            علي الاحمدي
 * Author URI:        https://alahm7dy.com
 * Text Domain:       alahm7dy-woocommerce
 * WC requires at least: 5.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MPWA_VERSION',     '3.2.0' );
define( 'MPWA_PLUGIN_FILE', __FILE__ );
define( 'MPWA_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'MPWA_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Declare HPOS Compatibility with WooCommerce
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MPWA_PLUGIN_FILE, true );
    }
} );

require MPWA_PLUGIN_DIR . 'includes/core-import.php';

register_activation_hook( __FILE__, function() {
    MpwaLogger::create_table();
    MpwaPhoneBook::create_table();
    if ( ! get_option( 'mpedia_wagatewaywebhook_secret' ) ) {
        update_option( 'mpedia_wagatewaywebhook_secret', wp_generate_password( 48, false, false ), false );
    }
} );

// Check WooCommerce is active before initializing WooCommerce features
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>إضافة Alahm7dy WooCommerce WhatsApp:</strong> تتطلب تفعيل إضافة <code>WooCommerce</code> لتعمل بكامل مميزاتها.</p></div>';
        } );
    }
} );

new MpwaWooCommerce( MPWA_PLUGIN_FILE );
