<?php
defined( 'ABSPATH' ) || exit;

/**
 * Offline syntactic check of a VAT number.
 *
 * Uses the country patterns shipped in the same dataset that drives the rate
 * sync — no network call, no API key, no quota. A valid format says nothing
 * about whether the VAT is real; that is what EUVATR_Api is for.
 */
class EUVATR_Format {

    /**
     * Jurisdictions VIES validates that the dataset does not flag as EU
     * members: XI (Northern Ireland) stays inside the EU VAT system for goods.
     */
    const VIES_EXTRA = [ 'XI' ];

    public static function normalize( string $vat_id ): string {
        return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $vat_id ) ?? '' );
    }

    public static function country_code( string $vat_id ): string {
        return substr( self::normalize( $vat_id ), 0, 2 );
    }

    /**
     * @return array{normalized: string, country_code: string, valid_format: bool, vies_eligible: bool}
     */
    public static function check( string $vat_id ): array {
        $normalized = self::normalize( $vat_id );
        $country    = substr( $normalized, 0, 2 );
        $entry      = self::country( $country );

        $valid = false;
        if ( $entry !== null && ! empty( $entry['pattern'] ) ) {
            $valid = (bool) preg_match( '~' . $entry['pattern'] . '~', $normalized );
        }

        return [
            'normalized'    => $normalized,
            'country_code'  => $entry !== null ? $country : '',
            'valid_format'  => $valid,
            'vies_eligible' => self::is_vies_eligible( $country ),
        ];
    }

    /**
     * True when VIES can verify VAT numbers for this country (EU-27 + XI).
     */
    public static function is_vies_eligible( string $country_code ): bool {
        $country_code = strtoupper( $country_code );
        if ( in_array( $country_code, self::VIES_EXTRA, true ) ) {
            return true;
        }
        $entry = self::country( $country_code );
        return $entry !== null && ! empty( $entry['eu_member'] );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function country( string $country_code ): ?array {
        $data = EUVATR_Data::get();
        if ( ! is_array( $data ) || empty( $data['rates'] ) ) {
            return null;
        }
        return $data['rates'][ strtoupper( $country_code ) ] ?? null;
    }
}
