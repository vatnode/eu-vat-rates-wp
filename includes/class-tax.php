<?php
defined( 'ABSPATH' ) || exit;

/**
 * Which country's VAT rate applies to an EU buyer.
 *
 * With an OSS registration the buyer's own rate applies — that is what
 * WooCommerce does by default once the rates are in the table. Below the
 * €10,000 distance-selling threshold a seller is not registered and charges its
 * own rate to every EU buyer instead, so the taxable address is swapped for the
 * store's.
 *
 * Buyers outside the EU are left alone: no EU rate should apply to them, and
 * the sync no longer writes rates for those countries.
 */
class Vatnode_Tax {

    public static function init(): void {
        add_filter( 'woocommerce_customer_taxable_address', [ __CLASS__, 'maybe_tax_at_store_rate' ] );
    }

    /**
     * @param array<int, string> $address [ country, state, postcode, city ]
     * @return array<int, string>
     */
    public static function maybe_tax_at_store_rate( array $address ): array {
        if ( Vatnode_Settings::is_oss_registered() ) {
            return $address;
        }

        $buyer_country = strtoupper( (string) ( $address[0] ?? '' ) );
        if ( $buyer_country === '' || ! Vatnode_Format::is_vies_eligible( $buyer_country ) ) {
            return $address;
        }

        $base = wc_get_base_location();
        if ( empty( $base['country'] ) ) {
            return $address;
        }

        return [
            $base['country'],
            (string) ( $base['state'] ?? '' ),
            (string) WC()->countries->get_base_postcode(),
            (string) WC()->countries->get_base_city(),
        ];
    }
}
