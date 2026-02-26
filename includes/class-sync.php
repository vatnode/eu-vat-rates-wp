<?php
defined( 'ABSPATH' ) || exit;

/**
 * Syncs EU VAT rates into the WooCommerce tax-rates table.
 *
 * Strategy: upsert by country code.
 *   - If a standard rate for that country already exists → update it.
 *   - Otherwise → insert.
 *   - Rates no longer in the dataset are left untouched (user may have
 *     custom rates; we only manage what we know about).
 */
class EUVATR_Sync {

    const OPTION_LAST_SYNC    = 'euvatr_last_sync';
    const OPTION_LAST_VERSION = 'euvatr_last_version';
    const OPTION_LAST_ERROR   = 'euvatr_last_error';

    /**
     * Run a full sync. Returns true on success, false on failure.
     */
    public static function run(): bool {
        $data = EUVATR_Data::fetch(); // always force a fresh fetch on explicit sync

        if ( $data === null ) {
            update_option( self::OPTION_LAST_ERROR, 'Could not fetch data from source.' );
            return false;
        }

        global $wpdb;

        $rates_table     = $wpdb->prefix . 'woocommerce_tax_rates';
        $locations_table = $wpdb->prefix . 'woocommerce_tax_rate_locations';
        $synced          = 0;

        foreach ( $data['rates'] as $country_code => $country ) {
            $standard_rate = (float) $country['standard'];
            $country_code  = strtoupper( $country_code );

            // Check for existing managed rate
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT tax_rate_id FROM {$rates_table}
                 WHERE tax_rate_country = %s
                   AND tax_rate_class   = ''
                   AND tax_rate_name    = 'VAT'
                 LIMIT 1",
                $country_code
            ) );

            if ( $existing_id ) {
                // Update
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->update(
                    $rates_table,
                    [ 'tax_rate' => number_format( $standard_rate, 4, '.', '' ) ],
                    [ 'tax_rate_id' => (int) $existing_id ],
                    [ '%s' ],
                    [ '%d' ]
                );
            } else {
                // Insert rate
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->insert( $rates_table, [
                    'tax_rate_country'  => $country_code,
                    'tax_rate_state'    => '',
                    'tax_rate'          => number_format( $standard_rate, 4, '.', '' ),
                    'tax_rate_name'     => 'VAT',
                    'tax_rate_priority' => 1,
                    'tax_rate_compound' => 0,
                    'tax_rate_shipping' => 1,
                    'tax_rate_order'    => $synced,
                    'tax_rate_class'    => '', // '' = Standard rate class
                ] );

                $new_id = (int) $wpdb->insert_id;

                // Link to country location
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->insert( $locations_table, [
                    'location_code' => $country_code,
                    'location_type' => 'country',
                    'tax_rate_id'   => $new_id,
                ] );
            }

            $synced++;
        }

        // Bust WooCommerce tax cache
        WC_Cache_Helper::invalidate_cache_group( 'taxes' );
        delete_transient( 'wc_tax_rates_' . md5( serialize( [] ) ) );

        // Record success
        update_option( self::OPTION_LAST_SYNC,    current_time( 'mysql' ) );
        update_option( self::OPTION_LAST_VERSION, $data['version'] ?? '' );
        delete_option( self::OPTION_LAST_ERROR );

        return true;
    }

    public static function last_sync(): string {
        return (string) get_option( self::OPTION_LAST_SYNC, '' );
    }

    public static function last_version(): string {
        return (string) get_option( self::OPTION_LAST_VERSION, '' );
    }

    public static function last_error(): string {
        return (string) get_option( self::OPTION_LAST_ERROR, '' );
    }
}
