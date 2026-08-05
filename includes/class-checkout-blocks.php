<?php
defined( 'ABSPATH' ) || exit;

/**
 * VAT number field on the block checkout (Additional Checkout Fields API).
 *
 * Registered in the `contact` location so the value travels with the
 * cart/update-customer request — that is what lets VAT disappear from the total
 * while the shopper is still on the page, instead of only after placing the
 * order.
 */
class EUVATR_Checkout_Blocks {

    const FIELD_ID = 'vatnode/vat-number';
    const META_KEY = '_wc_other/vatnode/vat-number';

    public static function init(): void {
        if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
            return; // WooCommerce older than the Additional Checkout Fields API.
        }

        add_action( 'woocommerce_init', [ __CLASS__, 'register_field' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_script' ] );
        add_action( 'woocommerce_store_api_cart_update_customer_from_request', [ __CLASS__, 'refresh_exemption' ], 10, 2 );
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ __CLASS__, 'attach_to_order' ], 10, 2 );
    }

    public static function register_field(): void {
        woocommerce_register_additional_checkout_field( [
            'id'                => self::FIELD_ID,
            'label'             => __( 'VAT number', 'eu-vat-rates-woo' ),
            'location'          => 'contact',
            'type'              => 'text',
            'required'          => EUVATR_Settings::is_field_required(),
            'attributes'        => [
                'autocomplete'   => 'off',
                'aria-describedby' => 'vatnode-vat-number-description',
            ],
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
            'validate_callback' => [ __CLASS__, 'validate' ],
        ] );
    }

    public static function enqueue_script(): void {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }
        wp_enqueue_script(
            'euvatr-blocks-checkout',
            EUVATR_URL . 'assets/blocks-checkout.js',
            [ 'wp-api-fetch', 'wp-data' ],
            EUVATR_VERSION,
            true
        );
    }

    public static function sanitize( $value ): string {
        return EUVATR_Format::normalize( (string) $value );
    }

    /**
     * @return WP_Error|void
     */
    public static function validate( $value ) {
        $vat_number = (string) $value;
        if ( $vat_number === '' ) {
            return;
        }

        $evaluation = EUVATR_Validator::evaluate( $vat_number, self::billing_country() );

        if ( $evaluation['blocking'] ) {
            return new WP_Error( 'euvatr_vat_invalid', $evaluation['message'] );
        }
    }

    public static function refresh_exemption( WC_Customer $customer, WP_REST_Request $request ): void {
        $evaluation = EUVATR_Validator::evaluate(
            self::vat_from_request( $request, $customer ),
            (string) $customer->get_billing_country()
        );

        EUVATR_Validator::apply_exemption( $evaluation['exempt'] );
    }

    public static function attach_to_order( WC_Order $order, WP_REST_Request $request ): void {
        $customer   = WC()->customer ?? null;
        $evaluation = EUVATR_Validator::evaluate(
            self::vat_from_request( $request, $customer instanceof WC_Customer ? $customer : null ),
            (string) $order->get_billing_country()
        );

        if ( $evaluation['status'] === EUVATR_Validator::STATUS_EMPTY ) {
            return;
        }

        EUVATR_Validator::apply_exemption( $evaluation['exempt'] );

        // The Store API stamps `is_vat_exempt` on the order before this hook
        // runs, and WC_Abstract_Order::calculate_taxes() reads that meta rather
        // than the customer — so it has to be set here, or the recalculation
        // below puts the tax straight back on.
        $order->update_meta_data( 'is_vat_exempt', $evaluation['exempt'] ? 'yes' : 'no' );

        // The order was built from a cart that was totalled before the
        // exemption was known — recalculate so the customer is charged the
        // amount the reverse charge implies.
        $order->calculate_taxes();
        $order->calculate_totals( false );

        EUVATR_Order::save( $order, $evaluation );
    }

    private static function vat_from_request( WP_REST_Request $request, ?WC_Customer $customer ): string {
        $fields = $request->get_param( 'additional_fields' );
        if ( is_array( $fields ) && isset( $fields[ self::FIELD_ID ] ) ) {
            return (string) $fields[ self::FIELD_ID ];
        }

        if ( $customer instanceof WC_Customer ) {
            return (string) $customer->get_meta( self::META_KEY );
        }

        return '';
    }

    private static function billing_country(): string {
        $customer = WC()->customer ?? null;
        return $customer instanceof WC_Customer ? (string) $customer->get_billing_country() : '';
    }
}
