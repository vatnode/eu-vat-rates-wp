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

        // Plugin versions up to 1.0.0 wrote a `country` location row per rate,
        // which silently stopped WooCommerce from matching those rates at all.
        // Drop them for the rates this plugin manages. Table names come from
        // $wpdb->prefix, never from input, so they cannot be placeholders.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query(
            "DELETE l FROM {$locations_table} l
             INNER JOIN {$rates_table} r ON r.tax_rate_id = l.tax_rate_id
             WHERE l.location_type = 'country'
               AND r.tax_rate_name = 'VAT'
               AND r.tax_rate_class = ''"
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        foreach ( $data['rates'] as $country_code => $country ) {
            $country_code = strtoupper( $country_code );

            // Only the EU VAT area belongs in the tax table. A seller in the EU
            // does not charge Norwegian or Swiss VAT to a buyer there — that is
            // an export, and writing those rates would make WooCommerce add tax
            // that is not owed.
            if ( ! EUVATR_Format::is_vies_eligible( $country_code ) ) {
                continue;
            }

            $standard_rate = (float) $country['standard'];

            // Check for existing managed rate
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT tax_rate_id FROM {$rates_table}
                 WHERE tax_rate_country = %s
                   AND tax_rate_class   = ''
                   AND tax_rate_name    = 'VAT'
                 LIMIT 1",
                $country_code
            ) );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

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
                // Insert rate. The country lives in tax_rate_country itself —
                // woocommerce_tax_rate_locations is only for postcode and city
                // restrictions, and a row there makes WC_Tax::find_rates()
                // require a match that a country-wide rate can never satisfy.
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
            }

            $synced++;
        }

        self::remove_non_eu_rates( $rates_table );

        // Bust WooCommerce tax cache
        WC_Cache_Helper::invalidate_cache_group( 'taxes' );
        delete_transient( 'wc_tax_rates_' . md5( serialize( [] ) ) );

        // Record success
        update_option( self::OPTION_LAST_SYNC,    current_time( 'mysql' ) );
        update_option( self::OPTION_LAST_VERSION, $data['version'] ?? '' );
        delete_option( self::OPTION_LAST_ERROR );

        return true;
    }

    /**
     * Versions up to 1.1.0 wrote a rate for every country in the dataset,
     * including non-EU ones. Those rows made WooCommerce charge, say, Norwegian
     * VAT on an export — remove the ones this plugin created.
     */
    private static function remove_non_eu_rates( string $rates_table ): void {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $managed = $wpdb->get_results(
            "SELECT tax_rate_id, tax_rate_country FROM {$rates_table}
             WHERE tax_rate_name = 'VAT' AND tax_rate_class = ''"
        );

        foreach ( $managed as $rate ) {
            if ( EUVATR_Format::is_vies_eligible( (string) $rate->tax_rate_country ) ) {
                continue;
            }
            $wpdb->delete( $rates_table, [ 'tax_rate_id' => (int) $rate->tax_rate_id ], [ '%d' ] );
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    public static function last_sync(): string {
        return (string) get_option( self::OPTION_LAST_SYNC, '' );
    }

    /**
     * Last sync in the site's own date format — the stored value is a machine
     * timestamp and should never be shown as-is.
     */
    public static function last_sync_display(): string {
        $stored = self::last_sync();
        if ( $stored === '' ) {
            return '';
        }
        $timestamp = strtotime( $stored );
        if ( ! $timestamp ) {
            return '';
        }
        return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
    }

    public static function last_version(): string {
        return (string) get_option( self::OPTION_LAST_VERSION, '' );
    }

    public static function last_error(): string {
        return (string) get_option( self::OPTION_LAST_ERROR, '' );
    }
}
