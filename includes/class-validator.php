<?php
defined( 'ABSPATH' ) || exit;

/**
 * Decides what a submitted VAT number means for a given order.
 *
 * Everything that can be answered offline (format, country eligibility, whether
 * the sale is even cross-border) is answered offline and for free. Only a VAT
 * number that could actually trigger a reverse charge is sent to vatnode.
 *
 * Fail-open by design: when the key is missing, the quota is spent or VIES is
 * down, the status is `unverified` — VAT is charged as usual and the order goes
 * through. A checkout must never be blocked by an upstream we do not control.
 *
 * A VAT number is checked once and the answer — including the VIES consultation
 * number — is reused from the local 24-hour cache for the rest of the checkout,
 * so one number costs one request no matter how often the totals refresh.
 */
class Vatnode_Validator {

    // Reverse charge applies.
    const STATUS_VALID = 'valid';
    // VIES answered: this VAT number does not exist.
    const STATUS_INVALID = 'invalid';
    // Fails the country regex — caught offline, no API call.
    const STATUS_FORMAT_INVALID = 'format_invalid';
    // VAT prefix does not match the billing country.
    const STATUS_COUNTRY_MISMATCH = 'country_mismatch';
    // Same country as the store: domestic sale, VAT is due.
    const STATUS_DOMESTIC = 'domestic';
    // Not an EU/VIES country, or the store itself is outside the EU.
    const STATUS_NOT_ELIGIBLE = 'not_eligible';
    // Could not check (no key, quota spent, VIES down). VAT is charged.
    const STATUS_UNVERIFIED = 'unverified';
    // No VAT number submitted.
    const STATUS_EMPTY = 'empty';

    /**
     * @return array{
     *   status: string,
     *   exempt: bool,
     *   blocking: bool,
     *   message: string,
     *   vat_number: string,
     *   country_code: string,
     *   data: array<string, mixed>|null
     * }
     */
    public static function evaluate( string $vat_id, string $billing_country ): array {
        $billing_country = Vatnode_Format::iso_code( trim( $billing_country ) );
        $format          = Vatnode_Format::check( $vat_id );
        $vat_number      = $format['normalized'];

        if ( $vat_number === '' ) {
            return self::result( self::STATUS_EMPTY, false, false, '', '', '' );
        }

        // No dataset means nothing can be decided offline. Calling the number
        // invalid here would block every B2B checkout for as long as the source
        // is unreachable — exactly the failure this plugin promises not to have.
        if ( ! $format['data_available'] ) {
            return self::result(
                self::STATUS_UNVERIFIED,
                false,
                false,
                __( 'VAT number could not be verified — VAT charged as usual.', 'vatnode-eu-vat-rates' ),
                $vat_number,
                ''
            );
        }

        $store_country = self::store_country();

        if ( ! Vatnode_Format::is_vies_eligible( $store_country ) ) {
            return self::result(
                self::STATUS_NOT_ELIGIBLE,
                false,
                false,
                __( 'Reverse charge applies only to stores based in the EU.', 'vatnode-eu-vat-rates' ),
                $vat_number,
                $format['country_code']
            );
        }

        if ( ! $format['valid_format'] || ! $format['vies_eligible'] ) {
            return self::result(
                self::STATUS_FORMAT_INVALID,
                false,
                true,
                __( 'This does not look like a valid EU VAT number. Check it, or leave the field empty.', 'vatnode-eu-vat-rates' ),
                $vat_number,
                $format['country_code']
            );
        }

        $vat_country = $format['country_code'];

        if ( $billing_country !== '' && $billing_country !== $vat_country ) {
            return self::result(
                self::STATUS_COUNTRY_MISMATCH,
                false,
                true,
                sprintf(
                    /* translators: 1: VAT number country code, 2: billing country code */
                    __( 'The VAT number is registered in %1$s but the billing address is in %2$s. They must match.', 'vatnode-eu-vat-rates' ),
                    $vat_country,
                    $billing_country
                ),
                $vat_number,
                $vat_country
            );
        }

        if ( $vat_country === Vatnode_Format::iso_code( $store_country ) ) {
            return self::result(
                self::STATUS_DOMESTIC,
                false,
                false,
                __( 'Domestic sale — VAT is charged as usual.', 'vatnode-eu-vat-rates' ),
                $vat_number,
                $vat_country
            );
        }

        if ( ! Vatnode_Settings::is_validation_active() ) {
            return self::result(
                self::STATUS_UNVERIFIED,
                false,
                false,
                __( 'VAT number could not be verified — VAT charged as usual.', 'vatnode-eu-vat-rates' ),
                $vat_number,
                $vat_country
            );
        }

        $response = Vatnode_Api::validate( $vat_number );

        if ( ! $response['ok'] ) {
            if ( $response['error_code'] === 'INVALID_FORMAT' ) {
                return self::result(
                    self::STATUS_FORMAT_INVALID,
                    false,
                    true,
                    __( 'This does not look like a valid EU VAT number. Check it, or leave the field empty.', 'vatnode-eu-vat-rates' ),
                    $vat_number,
                    $vat_country
                );
            }

            return self::result(
                self::STATUS_UNVERIFIED,
                false,
                false,
                $response['message'],
                $vat_number,
                $vat_country
            );
        }

        if ( ! $response['valid'] ) {
            return self::result(
                self::STATUS_INVALID,
                false,
                true,
                __( 'This VAT number is not registered in VIES. Check it, or leave the field empty to pay VAT.', 'vatnode-eu-vat-rates' ),
                $vat_number,
                $vat_country,
                $response['data']
            );
        }

        return self::result(
            self::STATUS_VALID,
            true,
            false,
            __( 'VAT number verified — reverse charge applies.', 'vatnode-eu-vat-rates' ),
            $vat_number,
            $vat_country,
            $response['data']
        );
    }

    /**
     * Applies (or clears) the WooCommerce customer VAT exemption that drives
     * the reverse charge. WooCommerce drops every tax line when it is set.
     */
    public static function apply_exemption( bool $exempt ): void {
        $customer = WC()->customer ?? null;
        if ( ! $customer instanceof WC_Customer ) {
            return;
        }
        if ( $customer->get_is_vat_exempt() === $exempt ) {
            return;
        }
        $customer->set_is_vat_exempt( $exempt );
        // Store API optimises away deferred saves, so persist explicitly.
        $customer->save();
    }

    public static function store_country(): string {
        return strtoupper( (string) WC()->countries->get_base_country() );
    }

    /**
     * Human-readable reason recorded on the order.
     */
    public static function status_label( string $status ): string {
        $labels = [
            self::STATUS_VALID            => __( 'Verified — reverse charge applied', 'vatnode-eu-vat-rates' ),
            self::STATUS_INVALID          => __( 'Not registered in VIES', 'vatnode-eu-vat-rates' ),
            self::STATUS_FORMAT_INVALID   => __( 'Invalid format', 'vatnode-eu-vat-rates' ),
            self::STATUS_COUNTRY_MISMATCH => __( 'VAT country does not match billing country', 'vatnode-eu-vat-rates' ),
            self::STATUS_DOMESTIC         => __( 'Domestic sale — VAT charged', 'vatnode-eu-vat-rates' ),
            self::STATUS_NOT_ELIGIBLE     => __( 'Outside the EU VAT area', 'vatnode-eu-vat-rates' ),
            self::STATUS_UNVERIFIED       => __( 'Not verified — VAT charged', 'vatnode-eu-vat-rates' ),
            self::STATUS_EMPTY            => __( 'No VAT number provided', 'vatnode-eu-vat-rates' ),
        ];
        return $labels[ $status ] ?? $status;
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array{status: string, exempt: bool, blocking: bool, message: string, vat_number: string, country_code: string, data: array<string, mixed>|null}
     */
    private static function result(
        string $status,
        bool $exempt,
        bool $blocking,
        string $message,
        string $vat_number,
        string $country_code,
        ?array $data = null
    ): array {
        return [
            'status'       => $status,
            'exempt'       => $exempt,
            'blocking'     => $blocking,
            'message'      => $message,
            'vat_number'   => $vat_number,
            'country_code' => $country_code,
            'data'         => $data,
        ];
    }
}
