<?php
/**
 * Plugin Name:       EU VAT Validation and Reverse Charge for WooCommerce - vatnode
 * Plugin URI:        https://vatnode.dev/woocommerce
 * Description:       Keeps WooCommerce EU tax rates up to date from the European Commission TEDB, and validates customer VAT numbers against VIES to apply the reverse charge.
 * Version:           1.1.3
 * Author:            vatnode
 * Author URI:        https://vatnode.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   11.0
 * Text Domain:       vatnode-eu-vat-rates
 */

defined( 'ABSPATH' ) || exit;

define( 'EUVATR_VERSION', '1.1.3' );
define( 'EUVATR_FILE',    __FILE__ );
define( 'EUVATR_DIR',     plugin_dir_path( __FILE__ ) );
define( 'EUVATR_URL',     plugin_dir_url( __FILE__ ) );

require_once EUVATR_DIR . 'includes/class-settings.php';
require_once EUVATR_DIR . 'includes/class-data.php';
require_once EUVATR_DIR . 'includes/class-format.php';
require_once EUVATR_DIR . 'includes/class-api.php';
require_once EUVATR_DIR . 'includes/class-validator.php';
require_once EUVATR_DIR . 'includes/class-tax.php';
require_once EUVATR_DIR . 'includes/class-order.php';
require_once EUVATR_DIR . 'includes/class-checkout-classic.php';
require_once EUVATR_DIR . 'includes/class-checkout-blocks.php';
require_once EUVATR_DIR . 'includes/class-sync.php';
require_once EUVATR_DIR . 'includes/class-scheduler.php';
require_once EUVATR_DIR . 'admin/class-admin.php';

add_action( 'before_woocommerce_init', function (): void {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', EUVATR_FILE, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', EUVATR_FILE, true );
    }
} );

add_action( 'plugins_loaded', function (): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function (): void {
            echo '<div class="notice notice-error"><p>'
                . '<strong>EU VAT Rates &amp; VAT Number Validation</strong> '
                . esc_html__( 'requires WooCommerce to be active.', 'vatnode-eu-vat-rates' )
                . '</p></div>';
        } );
        return;
    }

    EUVATR_Scheduler::init();
    EUVATR_Admin::init();
    EUVATR_Order::init();
    EUVATR_Tax::init();
    EUVATR_Checkout_Classic::init();
    EUVATR_Checkout_Blocks::init();
} );

register_activation_hook( EUVATR_FILE, function (): void {
    EUVATR_Scheduler::schedule();
    // Run first sync shortly after activation
    wp_schedule_single_event( time() + 10, 'euvatr_do_sync' );
} );

register_deactivation_hook( EUVATR_FILE, [ EUVATR_Scheduler::class, 'unschedule' ] );
