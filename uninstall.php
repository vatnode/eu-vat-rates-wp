<?php
/**
 * Removes everything the plugin stored, including the API key.
 *
 * Synced tax rates are deliberately left in place: they live in the
 * WooCommerce tax table, the store may still be trading on them, and deleting
 * them on uninstall would silently change what customers are charged.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$euvatr_options = [
    'euvatr_api_key',
    'euvatr_validation_enabled',
    'euvatr_field_required',
    'euvatr_key_status',
    'euvatr_last_sync',
    'euvatr_last_version',
    'euvatr_last_error',
];

foreach ( $euvatr_options as $euvatr_option ) {
    delete_option( $euvatr_option );
}

delete_transient( 'euvatr_rates' );

// Cached VIES answers are one transient per VAT number, so they have to be
// matched by prefix rather than deleted by name.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_euvatr_vies_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_euvatr_vies_' ) . '%'
    )
);

wp_clear_scheduled_hook( 'euvatr_do_sync' );
