<?php
defined( 'ABSPATH' ) || exit;

/**
 * Offline syntactic check of a VAT number.
 *
 * Uses the country patterns shipped in the same dataset that drives the rate
 * sync — no network call, no API key, no quota. A valid format says nothing
 * about whether the VAT is real; that is what Vatnode_Api is for.
 */
class Vatnode_Format {

    /**
     * Jurisdictions VIES validates that the dataset does not flag as EU
     * members: XI (Northern Ireland) stays inside the EU VAT system for goods.
     */
    const VIES_EXTRA = [ 'XI' ];

    /**
     * VAT prefixes that are not the country's ISO code.
     *
     * Greece issues VAT numbers under EL while WooCommerce, the dataset and the
     * billing address all say GR. Without the mapping a Greek VAT number has no
     * country entry at all: the format check fails, the checkout is blocked, and
     * a domestic Greek sale is mistaken for a cross-border one.
     */
    const PREFIX_TO_ISO = [ 'EL' => 'GR' ];

    public static function normalize( string $vat_id ): string {
        return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $vat_id ) ?? '' );
    }

    public static function country_code( string $vat_id ): string {
        return self::iso_code( substr( self::normalize( $vat_id ), 0, 2 ) );
    }

    /**
     * The ISO country code behind a VAT prefix — GR for EL, otherwise itself.
     */
    public static function iso_code( string $code ): string {
        $code = strtoupper( $code );
        return self::PREFIX_TO_ISO[ $code ] ?? $code;
    }

    /**
     * @return array{normalized: string, country_code: string, valid_format: bool, vies_eligible: bool, data_available: bool}
     */
    public static function check( string $vat_id ): array {
        $normalized = self::normalize( $vat_id );
        $country    = self::iso_code( substr( $normalized, 0, 2 ) );
        $entry      = self::country( $country );

        $valid = false;
        if ( $entry !== null && ! empty( $entry['pattern'] ) ) {
            $valid = (bool) preg_match( '~' . $entry['pattern'] . '~', $normalized );
        }

        return [
            'normalized'     => $normalized,
            'country_code'   => $entry !== null ? $country : '',
            'valid_format'   => $valid,
            'vies_eligible'  => self::is_vies_eligible( $country ),
            'data_available' => self::has_data(),
        ];
    }

    /**
     * True when VIES can verify VAT numbers for this country (EU-27 + XI).
     */
    public static function is_vies_eligible( string $country_code ): bool {
        $country_code = self::iso_code( $country_code );
        if ( in_array( $country_code, self::VIES_EXTRA, true ) ) {
            return true;
        }
        $entry = self::country( $country_code );
        return $entry !== null && ! empty( $entry['eu_member'] );
    }

    /**
     * Whether the rate dataset is available at all. Nothing can be decided
     * offline without it, and "no data" must not read as "invalid number".
     */
    public static function has_data(): bool {
        $data = Vatnode_Data::get();
        return is_array( $data ) && ! empty( $data['rates'] );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function country( string $country_code ): ?array {
        $data = Vatnode_Data::get();
        if ( ! is_array( $data ) || empty( $data['rates'] ) ) {
            return null;
        }
        return $data['rates'][ self::iso_code( $country_code ) ] ?? null;
    }
}
