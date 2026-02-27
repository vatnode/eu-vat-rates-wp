<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page under WooCommerce → EU VAT Rates.
 */
class EUVATR_Admin {

    public static function init(): void {
        add_action( 'admin_menu',        [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_euvatr_sync', [ __CLASS__, 'handle_manual_sync' ] );
        add_action( 'admin_enqueue_scripts',  [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function add_menu(): void {
        add_submenu_page(
            'woocommerce',
            __( 'EU VAT Rates', 'eu-vat-rates-woo' ),
            __( 'EU VAT Rates', 'eu-vat-rates-woo' ),
            'manage_woocommerce',
            'eu-vat-rates',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function handle_manual_sync(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'eu-vat-rates-woo' ) );
        }
        check_admin_referer( 'euvatr_sync' );

        $success = EUVATR_Sync::run();
        $status  = $success ? 'success' : 'error';

        wp_safe_redirect( add_query_arg(
            [ 'page' => 'eu-vat-rates', 'synced' => $status ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    public static function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'eu-vat-rates' ) === false ) {
            return;
        }
        wp_enqueue_style(
            'euvatr-admin',
            EUVATR_URL . 'assets/admin.css',
            [],
            EUVATR_VERSION
        );
    }

    public static function render_page(): void {
        $data      = EUVATR_Data::get();
        $last_sync = EUVATR_Sync::last_sync();
        $last_ver  = EUVATR_Sync::last_version();
        $last_err  = EUVATR_Sync::last_error();
        $next_run  = EUVATR_Scheduler::next_run();
        $is_pro    = euvatr_is_pro();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $synced    = sanitize_key( $_GET['synced'] ?? '' );

        require EUVATR_DIR . 'admin/views/page-settings.php';
    }
}
