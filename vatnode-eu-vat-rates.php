<?php
/**
 * Plugin Name:       EU VAT Validation and Reverse Charge - vatnode
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

define( 'VATNODE_VERSION', '1.1.3' );
define( 'VATNODE_FILE',    __FILE__ );
define( 'VATNODE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'VATNODE_URL',     plugin_dir_url( __FILE__ ) );

require_once VATNODE_DIR . 'includes/class-settings.php';
require_once VATNODE_DIR . 'includes/class-data.php';
require_once VATNODE_DIR . 'includes/class-format.php';
require_once VATNODE_DIR . 'includes/class-api.php';
require_once VATNODE_DIR . 'includes/class-validator.php';
require_once VATNODE_DIR . 'includes/class-tax.php';
require_once VATNODE_DIR . 'includes/class-order.php';
require_once VATNODE_DIR . 'includes/class-checkout-classic.php';
require_once VATNODE_DIR . 'includes/class-checkout-blocks.php';
require_once VATNODE_DIR . 'includes/class-sync.php';
require_once VATNODE_DIR . 'includes/class-scheduler.php';
require_once VATNODE_DIR . 'admin/class-admin.php';

/**
 * Carries settings over from the `euvatr_` names used up to 1.1.2. Without it a
 * store that already had an API key would silently lose it on update, and stop
 * applying the reverse charge.
 */
function vatnode_migrate_legacy_prefix(): void {
    if ( get_option( 'vatnode_prefix_migrated' ) ) {
        return;
    }

    $names = [
        'api_key',
        'validation_enabled',
        'field_required',
        'key_status',
        'oss_registered',
        'rates_last_good',
        'last_sync',
        'last_version',
        'last_error',
    ];

    foreach ( $names as $name ) {
        $legacy = get_option( 'euvatr_' . $name, null );
        if ( $legacy !== null ) {
            update_option( 'vatnode_' . $name, $legacy );
            delete_option( 'euvatr_' . $name );
        }
    }

    wp_clear_scheduled_hook( 'euvatr_do_sync' );
    Vatnode_Scheduler::schedule();

    update_option( 'vatnode_prefix_migrated', '1' );
}

add_action( 'before_woocommerce_init', function (): void {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', VATNODE_FILE, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', VATNODE_FILE, true );
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

    vatnode_migrate_legacy_prefix();

    Vatnode_Scheduler::init();
    Vatnode_Admin::init();
    Vatnode_Order::init();
    Vatnode_Tax::init();
    Vatnode_Checkout_Classic::init();
    Vatnode_Checkout_Blocks::init();
} );

register_activation_hook( VATNODE_FILE, function (): void {
    Vatnode_Scheduler::schedule();
    // Run first sync shortly after activation
    wp_schedule_single_event( time() + 10, 'vatnode_do_sync' );
} );

register_deactivation_hook( VATNODE_FILE, [ Vatnode_Scheduler::class, 'unschedule' ] );
