<?php
defined( 'ABSPATH' ) || exit;

/**
 * VAT number field on the classic (shortcode) checkout.
 *
 * The Additional Checkout Fields API covers the block checkout only, so the
 * classic form is wired up separately here. Both paths funnel into
 * EUVATR_Validator, so the rules can never drift apart.
 */
class EUVATR_Checkout_Classic {

    const FIELD_KEY = 'billing_vat_number';

    public static function init(): void {
        add_filter( 'woocommerce_checkout_fields', [ __CLASS__, 'add_checkout_field' ] );
        add_filter( 'woocommerce_billing_fields', [ __CLASS__, 'add_billing_field' ], 10, 1 );
        add_action( 'woocommerce_checkout_update_order_review', [ __CLASS__, 'refresh_exemption' ] );
        add_filter( 'woocommerce_checkout_posted_data', [ __CLASS__, 'apply_exemption_before_totals' ] );
        add_action( 'woocommerce_after_checkout_validation', [ __CLASS__, 'validate' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'attach_to_order' ], 10, 2 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_script' ] );
    }

    public static function enqueue_script(): void {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }
        wp_enqueue_script(
            'euvatr-checkout',
            EUVATR_URL . 'assets/checkout.js',
            [ 'jquery' ],
            EUVATR_VERSION,
            true
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function add_checkout_field( array $fields ): array {
        $fields['billing'][ self::FIELD_KEY ] = self::field_definition();
        return $fields;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function add_billing_field( array $fields ): array {
        $fields[ self::FIELD_KEY ] = self::field_definition();
        return $fields;
    }

    /**
     * Runs on every AJAX cart refresh so the shopper sees VAT drop off the
     * total as soon as a valid number is entered.
     */
    public static function refresh_exemption( string $post_data ): void {
        $posted = [];
        parse_str( $post_data, $posted );

        $evaluation = EUVATR_Validator::evaluate(
            (string) ( $posted[ self::FIELD_KEY ] ?? '' ),
            (string) ( $posted['billing_country'] ?? '' )
        );

        EUVATR_Validator::apply_exemption( $evaluation['exempt'] );
    }

    /**
     * WooCommerce recalculates the cart from the posted data before it
     * validates or creates the order, so the exemption has to be in place by
     * this point — otherwise the order is built with tax still on it.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function apply_exemption_before_totals( array $data ): array {
        $evaluation = EUVATR_Validator::evaluate(
            (string) ( $data[ self::FIELD_KEY ] ?? '' ),
            (string) ( $data['billing_country'] ?? '' )
        );

        EUVATR_Validator::apply_exemption( $evaluation['exempt'] );

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function validate( array $data, WP_Error $errors ): void {
        $vat_number = (string) ( $data[ self::FIELD_KEY ] ?? '' );

        if ( $vat_number === '' ) {
            if ( EUVATR_Settings::is_field_required() ) {
                $errors->add( 'euvatr_vat_required', __( 'Please enter your VAT number.', 'vatnode-eu-vat-rates' ) );
            }
            return;
        }

        $evaluation = EUVATR_Validator::evaluate( $vat_number, (string) ( $data['billing_country'] ?? '' ) );

        if ( $evaluation['blocking'] ) {
            $errors->add( 'euvatr_vat_invalid', $evaluation['message'] );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function attach_to_order( WC_Order $order, array $data ): void {
        $evaluation = EUVATR_Validator::evaluate(
            (string) ( $data[ self::FIELD_KEY ] ?? '' ),
            (string) ( $data['billing_country'] ?? '' )
        );

        EUVATR_Order::save( $order, $evaluation );
    }

    /**
     * @return array<string, mixed>
     */
    private static function field_definition(): array {
        return [
            'type'        => 'text',
            'label'       => __( 'VAT number', 'vatnode-eu-vat-rates' ),
            'placeholder' => __( 'e.g. DE123456789', 'vatnode-eu-vat-rates' ),
            'description' => __( 'EU businesses outside our country: enter your VAT number to buy without VAT.', 'vatnode-eu-vat-rates' ),
            'required'    => EUVATR_Settings::is_field_required(),
            'class'       => [ 'form-row-wide' ],
            'priority'    => 125,
        ];
    }
}
