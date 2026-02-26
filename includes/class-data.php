<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fetches and caches EU VAT rates from the canonical data source.
 */
class EUVATR_Data {

    const SOURCE_URL   = 'https://raw.githubusercontent.com/vatnode/eu-vat-rates-data/main/data/eu-vat-rates-data.json';
    const TRANSIENT    = 'euvatr_rates';
    const CACHE_HOURS  = 25; // slightly over 24h so daily cron always wins

    /**
     * Returns the full rates array, fetching remotely if cache is stale.
     *
     * @return array{version: string, rates: array<string, array>}|null
     */
    public static function get(): ?array {
        $cached = get_transient( self::TRANSIENT );
        if ( $cached !== false ) {
            return $cached;
        }
        return self::fetch();
    }

    /**
     * Force a remote fetch and refresh the cache.
     *
     * @return array|null  Returns the dataset, or null on failure.
     */
    public static function fetch(): ?array {
        $response = wp_remote_get( self::SOURCE_URL, [
            'timeout'    => 15,
            'user-agent' => 'eu-vat-rates-woo/' . EUVATR_VERSION . '; ' . home_url(),
        ] );

        if ( is_wp_error( $response ) ) {
            self::log( 'Fetch failed: ' . $response->get_error_message() );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            self::log( "Fetch failed: HTTP {$code}" );
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['rates'] ) || ! is_array( $data['rates'] ) ) {
            self::log( 'Fetch failed: unexpected JSON structure' );
            return null;
        }

        set_transient( self::TRANSIENT, $data, HOUR_IN_SECONDS * self::CACHE_HOURS );
        return $data;
    }

    /**
     * Clear the local cache (forces re-fetch on next get()).
     */
    public static function clear_cache(): void {
        delete_transient( self::TRANSIENT );
    }

    private static function log( string $message ): void {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[EU VAT Rates] ' . $message );
        }
    }
}
