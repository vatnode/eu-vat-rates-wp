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

$vatnode_options = [
    'vatnode_api_key',
    'vatnode_validation_enabled',
    'vatnode_field_required',
    'vatnode_key_status',
    'vatnode_oss_registered',
    'vatnode_rates_last_good',
    'vatnode_last_sync',
    'vatnode_last_version',
    'vatnode_last_error',
    'vatnode_prefix_migrated',
];

foreach ( $vatnode_options as $vatnode_option ) {
    delete_option( $vatnode_option );
}

delete_transient( 'vatnode_rates' );

// Cached VIES answers are one transient per VAT number, so they have to be
// matched by prefix rather than deleted by name.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_vatnode_vies_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_vatnode_vies_' ) . '%'
    )
);

wp_clear_scheduled_hook( 'vatnode_do_sync' );
