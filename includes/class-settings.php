<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plugin options.
 *
 * Rates sync works with no configuration at all. VAT number validation is
 * opt-in and needs a vatnode API key.
 */
class EUVATR_Settings {

    const OPTION_API_KEY    = 'euvatr_api_key';
    const OPTION_VALIDATION = 'euvatr_validation_enabled';
    const OPTION_FIELD_REQ  = 'euvatr_field_required';
    const OPTION_KEY_STATUS = 'euvatr_key_status';
    const OPTION_OSS        = 'euvatr_oss_registered';

    public static function api_key(): string {
        return trim( (string) get_option( self::OPTION_API_KEY, '' ) );
    }

    public static function has_api_key(): bool {
        return self::api_key() !== '';
    }

    /**
     * VIES validation and reverse charge are active only when the merchant
     * enabled them AND supplied a key — without a key there is nothing to call.
     */
    public static function is_validation_active(): bool {
        return self::has_api_key() && self::is_validation_enabled();
    }

    /**
     * Defaults to on. Adding an API key is the deliberate step; a merchant who
     * has just pasted one should not have to hunt for a second switch before
     * anything happens. Nothing is called until a key exists.
     */
    public static function is_validation_enabled(): bool {
        return (bool) get_option( self::OPTION_VALIDATION, true );
    }

    public static function is_field_required(): bool {
        return (bool) get_option( self::OPTION_FIELD_REQ, false );
    }

    /**
     * Whether the store is registered for the EU One Stop Shop.
     *
     * Registered: cross-border B2C sales are taxed at the buyer's rate.
     * Not registered (below the €10,000 distance-selling threshold): the
     * seller's own rate applies to every EU buyer.
     *
     * Defaults to true so an existing install keeps charging what it charged
     * before this setting existed.
     */
    public static function is_oss_registered(): bool {
        return (bool) get_option( self::OPTION_OSS, true );
    }

    /**
     * Last known key state, refreshed on save and on every API call.
     *
     * @return array{state: string, message: string, checked_at: string}
     */
    public static function key_status(): array {
        $status = get_option( self::OPTION_KEY_STATUS, [] );
        return [
            'state'      => (string) ( $status['state'] ?? 'unknown' ),
            'message'    => (string) ( $status['message'] ?? '' ),
            'checked_at' => (string) ( $status['checked_at'] ?? '' ),
        ];
    }

    public static function set_key_status( string $state, string $message = '' ): void {
        update_option( self::OPTION_KEY_STATUS, [
            'state'      => $state,
            'message'    => $message,
            'checked_at' => current_time( 'mysql' ),
        ] );
    }

    public static function masked_key(): string {
        $key = self::api_key();
        if ( $key === '' ) {
            return '';
        }
        // Prefix only — the tail of a live key is not something to leave on
        // screen, in a screenshot or in a support ticket.
        $prefix = str_contains( $key, '_' )
            ? substr( $key, 0, strrpos( $key, '_' ) + 1 )
            : substr( $key, 0, 4 );

        return $prefix . str_repeat( '•', 12 );
    }
}
