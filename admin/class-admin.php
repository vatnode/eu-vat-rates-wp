<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page under WooCommerce → EU VAT Rates.
 */
class EUVATR_Admin {

    const SIGNUP_URL = 'https://vatnode.dev/woocommerce?ref=woo-plugin';

    public static function init(): void {
        add_action( 'admin_menu',                       [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_euvatr_sync',           [ __CLASS__, 'handle_manual_sync' ] );
        add_action( 'admin_post_euvatr_save_settings',  [ __CLASS__, 'handle_save_settings' ] );
        add_action( 'admin_post_euvatr_test_key',       [ __CLASS__, 'handle_test_key' ] );
        add_action( 'admin_enqueue_scripts',            [ __CLASS__, 'enqueue_assets' ] );
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
        self::guard( 'euvatr_sync' );

        $success = EUVATR_Sync::run();

        self::redirect( [ 'synced' => $success ? 'success' : 'error' ] );
    }

    public static function handle_save_settings(): void {
        self::guard( 'euvatr_save_settings' );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
        $post = wp_unslash( $_POST );

        if ( ! empty( $post['remove_key'] ) ) {
            delete_option( EUVATR_Settings::OPTION_API_KEY );
            delete_option( EUVATR_Settings::OPTION_KEY_STATUS );
        } else {
            $key = sanitize_text_field( (string) ( $post['api_key'] ?? '' ) );
            // An empty box means "leave the stored key alone", not "delete it" —
            // the field never renders the real key back to the browser.
            if ( $key !== '' ) {
                update_option( EUVATR_Settings::OPTION_API_KEY, $key );
                EUVATR_Settings::set_key_status( 'unknown' );
            }
        }

        update_option( EUVATR_Settings::OPTION_VALIDATION, ! empty( $post['validation_enabled'] ) );
        update_option( EUVATR_Settings::OPTION_FIELD_REQ, ! empty( $post['field_required'] ) );

        self::redirect( [ 'saved' => '1' ] );
    }

    public static function handle_test_key(): void {
        self::guard( 'euvatr_test_key' );

        $result = EUVATR_Api::test_key();

        self::redirect( [
            'tested'  => $result['ok'] ? 'success' : 'error',
            'message' => rawurlencode( $result['message'] ),
        ] );
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
        $data       = EUVATR_Data::get();
        $last_sync  = EUVATR_Sync::last_sync_display();
        $last_ver   = EUVATR_Sync::last_version();
        $last_err   = EUVATR_Sync::last_error();
        $next_run   = EUVATR_Scheduler::next_run();
        $has_key    = EUVATR_Settings::has_api_key();
        $masked_key = EUVATR_Settings::masked_key();
        $validation = EUVATR_Settings::is_validation_active();
        $enabled    = (bool) get_option( EUVATR_Settings::OPTION_VALIDATION, false );
        $required   = EUVATR_Settings::is_field_required();
        $key_status = EUVATR_Settings::key_status();
        $signup_url = self::SIGNUP_URL;

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state.
        $synced  = sanitize_key( $_GET['synced'] ?? '' );
        $saved   = sanitize_key( $_GET['saved'] ?? '' );
        $tested  = sanitize_key( $_GET['tested'] ?? '' );
        $message = sanitize_text_field( rawurldecode( (string) ( $_GET['message'] ?? '' ) ) );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        require EUVATR_DIR . 'admin/views/page-settings.php';
    }

    private static function guard( string $nonce_action ): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'eu-vat-rates-woo' ) );
        }
        check_admin_referer( $nonce_action );
    }

    /**
     * @param array<string, string> $args
     */
    private static function redirect( array $args ): void {
        wp_safe_redirect( add_query_arg(
            array_merge( [ 'page' => 'eu-vat-rates' ], $args ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }
}
