<?php
defined( 'ABSPATH' ) || exit;

/**
 * Serves the EU VAT rate dataset and refreshes it from the canonical source.
 *
 * Three layers on purpose. The plugin ships with a full copy of the dataset, so
 * it works with no network access at all; the option holds the last refreshed
 * dataset that was known to be good; the transient is the hot cache. Rates and
 * VAT-number patterns are read on the checkout path, so a source that is
 * briefly unreachable must never leave the store with no data.
 */
class EUVATR_Data {

    const SOURCE_URL   = 'https://raw.githubusercontent.com/vatnode/eu-vat-rates-data/main/data/eu-vat-rates-data.json';
    const BUNDLED_FILE = 'data/eu-vat-rates-data.json';
    const TRANSIENT    = 'euvatr_rates';
    const OPTION_LAST_GOOD = 'euvatr_rates_last_good';
    const CACHE_HOURS  = 25; // slightly over 24h so daily cron always wins

    /**
     * Returns the full rates array. Never performs a blocking remote request:
     * the bundled copy answers immediately and a refresh runs in the background.
     *
     * @return array{version: string, rates: array<string, array>}|null
     */
    public static function get(): ?array {
        $cached = get_transient( self::TRANSIENT );
        if ( self::is_dataset( $cached ) ) {
            return $cached;
        }

        self::schedule_refresh();

        return self::newest( get_option( self::OPTION_LAST_GOOD, null ), self::bundled() );
    }

    /**
     * The copy shipped inside the plugin. This is what makes the plugin usable
     * with no remote call whatsoever — a plugin update brings fresh rates too.
     *
     * @return array|null
     */
    public static function bundled(): ?array {
        static $bundled = null;

        if ( $bundled === null ) {
            $decoded = wp_json_file_decode( EUVATR_DIR . self::BUNDLED_FILE, [ 'associative' => true ] );
            $bundled = self::is_dataset( $decoded ) ? $decoded : false;
        }

        return $bundled === false ? null : $bundled;
    }

    /**
     * Picks whichever dataset carries the later version. Versions are ISO dates,
     * so a string comparison is the right one.
     *
     * @param mixed $a
     * @param mixed $b
     * @return array|null
     */
    private static function newest( $a, $b ): ?array {
        if ( ! self::is_dataset( $a ) ) {
            return self::is_dataset( $b ) ? $b : null;
        }
        if ( ! self::is_dataset( $b ) ) {
            return $a;
        }

        return (string) ( $b['version'] ?? '' ) > (string) ( $a['version'] ?? '' ) ? $b : $a;
    }

    /**
     * Force a remote fetch and refresh both caches.
     *
     * @return array|null  Returns the dataset, or null on failure.
     */
    public static function fetch(): ?array {
        $response = wp_remote_get( self::SOURCE_URL, [
            'timeout'    => 15,
            'user-agent' => 'vatnode-eu-vat-rates/' . EUVATR_VERSION . '; ' . home_url(),
        ] );

        if ( is_wp_error( $response ) ) {
            self::log( 'Fetch failed: ' . $response->get_error_message() );
            return self::fallback();
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            self::log( "Fetch failed: HTTP {$code}" );
            return self::fallback();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! self::is_dataset( $data ) ) {
            self::log( 'Fetch failed: unexpected JSON structure' );
            return self::fallback();
        }

        set_transient( self::TRANSIENT, $data, HOUR_IN_SECONDS * self::CACHE_HOURS );
        update_option( self::OPTION_LAST_GOOD, $data, false );

        return $data;
    }

    /**
     * Clear the hot cache (forces re-fetch on next get()). The last-good copy
     * stays: it is the safety net, not a cache.
     */
    public static function clear_cache(): void {
        delete_transient( self::TRANSIENT );
    }

    /**
     * @param mixed $data
     */
    private static function is_dataset( $data ): bool {
        return is_array( $data ) && ! empty( $data['rates'] ) && is_array( $data['rates'] );
    }

    /**
     * @return array|null
     */
    private static function fallback(): ?array {
        return self::newest( get_option( self::OPTION_LAST_GOOD, null ), self::bundled() );
    }

    /**
     * Ask WP-Cron for a sync on the next request instead of blocking this one.
     */
    private static function schedule_refresh(): void {
        // WP-Cron drops a duplicate of the same hook within its own 10-minute
        // window, so this cannot pile up across concurrent requests.
        wp_schedule_single_event( time(), EUVATR_Scheduler::HOOK );
    }

    private static function log( string $message ): void {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[EU VAT Rates] ' . $message );
        }
    }
}
