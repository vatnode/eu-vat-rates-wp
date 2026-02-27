<?php
/**
 * Plugin Name:       EU VAT Rates for WooCommerce
 * Plugin URI:        https://vatnode.dev
 * Description:       Keeps WooCommerce EU tax rates always up to date. Syncs daily from the European Commission TEDB (official source).
 * Version:           1.0.0
 * Author:            vatnode
 * Author URI:        https://vatnode.dev
 * License:           GPL-2.0-or-later
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * WC requires at least: 8.0
 * WC tested up to:   9.4
 * Text Domain:       eu-vat-rates-woo
 */

defined( 'ABSPATH' ) || exit;

define( 'EUVATR_VERSION', '1.0.0' );
define( 'EUVATR_FILE',    __FILE__ );
define( 'EUVATR_DIR',     plugin_dir_path( __FILE__ ) );
define( 'EUVATR_URL',     plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// Freemius bootstrap (fill in IDs after creating product at freemius.com)
// ---------------------------------------------------------------------------
function euvatr_fs(): mixed {
    global $euvatr_fs;
    if ( ! isset( $euvatr_fs ) ) {
        require_once EUVATR_DIR . 'freemius/start.php';
        $euvatr_fs = fs_dynamic_init( [
            'id'             => 'REPLACE_WITH_FREEMIUS_ID',
            'slug'           => 'eu-vat-rates-woo',
            'type'           => 'plugin',
            'public_key'     => 'REPLACE_WITH_FREEMIUS_PUBLIC_KEY',
            'is_premium'     => true,
            'has_addons'     => false,
            'has_paid_plans' => true,
            'trial'          => [ 'days' => 14, 'is_require_payment' => false ],
            'menu'           => [
                'slug'   => 'eu-vat-rates',
                'parent' => [ 'slug' => 'woocommerce' ],
            ],
        ] );
    }
    return $euvatr_fs;
}
// euvatr_fs();          // ← uncomment once Freemius SDK is added
// do_action( 'euvatr_fs_loaded' );

// ---------------------------------------------------------------------------
// Pro license check
// ---------------------------------------------------------------------------

/**
 * Returns true if the current site has an active Pro license or trial.
 *
 * When the Freemius SDK is not installed (local dev), returns true so the
 * full plugin is testable without a live account. In production the SDK
 * is always present and the Freemius check is authoritative.
 */
function euvatr_is_pro(): bool {
    if ( ! function_exists( 'fs_dynamic_init' ) ) {
        // SDK not installed — local dev fallback.
        return true;
    }
    return euvatr_fs()->is_paying() || euvatr_fs()->is_trial();
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
require_once EUVATR_DIR . 'includes/class-data.php';
require_once EUVATR_DIR . 'includes/class-sync.php';
require_once EUVATR_DIR . 'includes/class-scheduler.php';
require_once EUVATR_DIR . 'admin/class-admin.php';

add_action( 'plugins_loaded', function (): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function (): void {
            echo '<div class="notice notice-error"><p>'
                . '<strong>EU VAT Rates for WooCommerce</strong> '
                . esc_html__( 'requires WooCommerce to be active.', 'eu-vat-rates-woo' )
                . '</p></div>';
        } );
        return;
    }

    EUVATR_Scheduler::init();
    EUVATR_Admin::init();
} );

register_activation_hook( EUVATR_FILE, function (): void {
    EUVATR_Scheduler::schedule();
    // Run first sync shortly after activation
    wp_schedule_single_event( time() + 10, 'euvatr_do_sync' );
} );

register_deactivation_hook( EUVATR_FILE, [ EUVATR_Scheduler::class, 'unschedule' ] );
