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
            __( 'EU VAT Rates', 'vatnode-eu-vat-rates' ),
            __( 'EU VAT Rates', 'vatnode-eu-vat-rates' ),
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

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
        $remove_key = isset( $_POST['remove_key'] );
        $key        = isset( $_POST['api_key'] )
            ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) )
            : '';
        $validation = isset( $_POST['validation_enabled'] );
        $required   = isset( $_POST['field_required'] );
        $oss        = isset( $_POST['oss_registered'] );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( $remove_key ) {
            delete_option( EUVATR_Settings::OPTION_API_KEY );
            delete_option( EUVATR_Settings::OPTION_KEY_STATUS );
        } elseif ( $key !== '' ) {
            // An empty box means "leave the stored key alone", not "delete it" —
            // the field never renders the real key back to the browser.
            update_option( EUVATR_Settings::OPTION_API_KEY, $key );
            EUVATR_Settings::set_key_status( 'unknown' );
        }

        update_option( EUVATR_Settings::OPTION_VALIDATION, $validation );
        update_option( EUVATR_Settings::OPTION_FIELD_REQ, $required );
        update_option( EUVATR_Settings::OPTION_OSS, $oss );

        self::redirect( [ 'saved' => '1' ] );
    }

    public static function handle_test_key(): void {
        self::guard( 'euvatr_test_key' );

        $result = EUVATR_Api::test_key();

        self::redirect( [
            'tested'  => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
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
        $oss        = EUVATR_Settings::is_oss_registered();
        $store      = EUVATR_Validator::store_country();
        $key_status = EUVATR_Settings::key_status();
        $signup_url = self::SIGNUP_URL;

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state.
        $synced  = isset( $_GET['synced'] ) ? sanitize_key( wp_unslash( $_GET['synced'] ) ) : '';
        $saved   = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : '';
        $tested  = isset( $_GET['tested'] ) ? sanitize_key( wp_unslash( $_GET['tested'] ) ) : '';
        $message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        require EUVATR_DIR . 'admin/views/page-settings.php';
    }

    private static function guard( string $nonce_action ): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'vatnode-eu-vat-rates' ) );
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
