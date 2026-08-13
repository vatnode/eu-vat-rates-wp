<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page under WooCommerce → EU VAT Rates.
 */
class Vatnode_Admin {

    // For a merchant with no key yet: the page that explains what the key
    // unlocks and what it costs.
    const SIGNUP_URL = 'https://vatnode.dev/woocommerce?ref=woo-plugin';

    // Straight to the keys screen. A signed-out visitor is sent through login
    // and returned here, so one link serves both cases.
    const KEYS_URL = 'https://vatnode.dev/dashboard/api-keys?ref=woo-plugin';

    const SETTINGS_SLUG = 'vatnode-settings';

    public static function init(): void {
        add_action( 'admin_menu',                       [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_vatnode_sync',           [ __CLASS__, 'handle_manual_sync' ] );
        add_action( 'admin_post_vatnode_save_settings',  [ __CLASS__, 'handle_save_settings' ] );
        add_action( 'admin_post_vatnode_test_key',       [ __CLASS__, 'handle_test_key' ] );
        add_action( 'admin_enqueue_scripts',            [ __CLASS__, 'enqueue_assets' ] );

        add_filter(
            'plugin_action_links_' . plugin_basename( VATNODE_FILE ),
            [ __CLASS__, 'add_action_links' ]
        );
    }

    public static function add_menu(): void {
        add_submenu_page(
            'woocommerce',
            __( 'EU VAT Rates', 'vatnode-eu-vat-rates' ),
            __( 'EU VAT Rates', 'vatnode-eu-vat-rates' ),
            'manage_woocommerce',
            self::SETTINGS_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * Row links on Plugins → Installed Plugins. Without them the settings
     * screen is buried under the WooCommerce menu right after activation.
     *
     * @param array<int, string> $links
     * @return array<int, string>
     */
    public static function add_action_links( array $links ): array {
        $own = [
            '<a href="' . esc_url( self::settings_url() ) . '">'
                . esc_html__( 'Settings', 'vatnode-eu-vat-rates' ) . '</a>',
        ];

        if ( ! Vatnode_Settings::has_api_key() ) {
            $own[] = '<a href="' . esc_url( self::SIGNUP_URL ) . '" target="_blank" rel="noopener noreferrer">'
                . esc_html__( 'Get an API key', 'vatnode-eu-vat-rates' ) . '</a>';
        }

        return array_merge( $own, $links );
    }

    public static function settings_url(): string {
        return admin_url( 'admin.php?page=' . self::SETTINGS_SLUG );
    }

    public static function handle_manual_sync(): void {
        self::guard( 'vatnode_sync' );

        $success = Vatnode_Sync::run();

        self::redirect( [ 'synced' => $success ? 'success' : 'error' ] );
    }

    public static function handle_save_settings(): void {
        self::guard( 'vatnode_save_settings' );

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
            delete_option( Vatnode_Settings::OPTION_API_KEY );
            delete_option( Vatnode_Settings::OPTION_KEY_STATUS );
        } elseif ( $key !== '' ) {
            // An empty box means "leave the stored key alone", not "delete it" —
            // the field never renders the real key back to the browser.
            update_option( Vatnode_Settings::OPTION_API_KEY, $key );
            Vatnode_Settings::set_key_status( 'unknown' );
        }

        update_option( Vatnode_Settings::OPTION_VALIDATION, $validation );
        update_option( Vatnode_Settings::OPTION_FIELD_REQ, $required );
        update_option( Vatnode_Settings::OPTION_OSS, $oss );

        // Check a freshly pasted key straight away. A merchant who mistyped it
        // should find out here, not from the first B2B order that quietly
        // charged VAT it did not need to.
        if ( ! $remove_key && $key !== '' ) {
            $test = Vatnode_Api::test_key();

            self::redirect( [
                'saved'   => '1',
                'tested'  => $test['ok'] ? 'success' : 'error',
                'message' => $test['message'],
            ] );
        }

        self::redirect( [ 'saved' => '1' ] );
    }

    public static function handle_test_key(): void {
        self::guard( 'vatnode_test_key' );

        $result = Vatnode_Api::test_key();

        self::redirect( [
            'tested'  => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ] );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, self::SETTINGS_SLUG ) === false ) {
            return;
        }
        wp_enqueue_style(
            'vatnode-admin',
            VATNODE_URL . 'assets/admin.css',
            [],
            VATNODE_VERSION
        );
    }

    public static function render_page(): void {
        $data       = Vatnode_Data::get();
        $last_sync  = Vatnode_Sync::last_sync_display();
        $last_ver   = Vatnode_Sync::last_version();
        $last_err   = Vatnode_Sync::last_error();
        $next_run   = Vatnode_Scheduler::next_run();
        $has_key    = Vatnode_Settings::has_api_key();
        $masked_key = Vatnode_Settings::masked_key();
        $validation = Vatnode_Settings::is_validation_active();
        $enabled    = Vatnode_Settings::is_validation_enabled();
        $required   = Vatnode_Settings::is_field_required();
        $oss        = Vatnode_Settings::is_oss_registered();
        $store      = Vatnode_Validator::store_country();
        $key_status = Vatnode_Settings::key_status();
        $signup_url = self::SIGNUP_URL;
        $keys_url   = self::KEYS_URL;

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state.
        $synced  = isset( $_GET['synced'] ) ? sanitize_key( wp_unslash( $_GET['synced'] ) ) : '';
        $saved   = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : '';
        $tested  = isset( $_GET['tested'] ) ? sanitize_key( wp_unslash( $_GET['tested'] ) ) : '';
        $message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        require VATNODE_DIR . 'admin/views/page-settings.php';
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
            array_merge( [ 'page' => self::SETTINGS_SLUG ], $args ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }
}
