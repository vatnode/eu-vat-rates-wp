<?php
defined( 'ABSPATH' ) || exit;

/**
 * Persists the VAT verdict on the order and shows it in the admin.
 *
 * The evidence (VIES consultation number, company name, timestamp) is written
 * once at checkout and never recalculated — an order must keep the answer that
 * was true when it was placed.
 */
class Vatnode_Order {

    const META_VAT_NUMBER   = '_vatnode_vat_number';
    const META_STATUS       = '_vatnode_vat_status';
    const META_COUNTRY      = '_vatnode_vat_country';
    const META_COMPANY      = '_vatnode_company_name';
    const META_CONSULTATION = '_vatnode_consultation_number';
    const META_SOURCE       = '_vatnode_source';
    const META_CHECKED_AT   = '_vatnode_checked_at';
    const META_REVERSE      = '_vatnode_reverse_charge';

    public static function init(): void {
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ __CLASS__, 'render_admin_panel' ] );
    }

    /**
     * @param array<string, mixed> $evaluation Result of Vatnode_Validator::evaluate().
     */
    public static function save( WC_Order $order, array $evaluation ): void {
        if ( $evaluation['status'] === Vatnode_Validator::STATUS_EMPTY ) {
            return;
        }

        $data = is_array( $evaluation['data'] ?? null ) ? $evaluation['data'] : [];

        $order->update_meta_data( self::META_VAT_NUMBER, $evaluation['vat_number'] );
        $order->update_meta_data( self::META_STATUS, $evaluation['status'] );
        $order->update_meta_data( self::META_COUNTRY, $evaluation['country_code'] );
        $order->update_meta_data( self::META_REVERSE, $evaluation['exempt'] ? 'yes' : 'no' );
        $order->update_meta_data( self::META_CHECKED_AT, gmdate( 'c' ) );

        if ( ! empty( $data['companyName'] ) ) {
            $order->update_meta_data( self::META_COMPANY, sanitize_text_field( (string) $data['companyName'] ) );
        }
        if ( ! empty( $data['consultationNumber'] ) ) {
            $order->update_meta_data( self::META_CONSULTATION, sanitize_text_field( (string) $data['consultationNumber'] ) );
        }
        if ( ! empty( $data['source'] ) ) {
            $order->update_meta_data( self::META_SOURCE, sanitize_text_field( (string) $data['source'] ) );
        }

        $order->add_order_note( sprintf(
            /* translators: 1: VAT number, 2: status label */
            __( 'VAT number %1$s — %2$s', 'vatnode-eu-vat-rates' ),
            $evaluation['vat_number'],
            Vatnode_Validator::status_label( $evaluation['status'] )
        ) );
    }

    public static function render_admin_panel( WC_Order $order ): void {
        $vat_number = (string) $order->get_meta( self::META_VAT_NUMBER );
        if ( $vat_number === '' ) {
            return;
        }

        $status       = (string) $order->get_meta( self::META_STATUS );
        $company      = (string) $order->get_meta( self::META_COMPANY );
        $consultation = (string) $order->get_meta( self::META_CONSULTATION );
        $checked_at   = (string) $order->get_meta( self::META_CHECKED_AT );
        $reverse      = $order->get_meta( self::META_REVERSE ) === 'yes';

        echo '<div class="vatnode-order-panel"><h3>' . esc_html__( 'EU VAT', 'vatnode-eu-vat-rates' ) . '</h3><p>';
        echo '<strong>' . esc_html__( 'VAT number:', 'vatnode-eu-vat-rates' ) . '</strong> <code>' . esc_html( $vat_number ) . '</code><br>';
        echo '<strong>' . esc_html__( 'Status:', 'vatnode-eu-vat-rates' ) . '</strong> ' . esc_html( Vatnode_Validator::status_label( $status ) ) . '<br>';
        echo '<strong>' . esc_html__( 'Reverse charge:', 'vatnode-eu-vat-rates' ) . '</strong> ' . ( $reverse ? esc_html__( 'Applied', 'vatnode-eu-vat-rates' ) : esc_html__( 'Not applied', 'vatnode-eu-vat-rates' ) ) . '<br>';

        if ( $company !== '' ) {
            echo '<strong>' . esc_html__( 'Registered name:', 'vatnode-eu-vat-rates' ) . '</strong> ' . esc_html( $company ) . '<br>';
        }
        if ( $consultation !== '' ) {
            echo '<strong>' . esc_html__( 'VIES consultation number:', 'vatnode-eu-vat-rates' ) . '</strong> <code>' . esc_html( $consultation ) . '</code><br>';
        }
        if ( $checked_at !== '' ) {
            $timestamp = strtotime( $checked_at );
            if ( $timestamp ) {
                echo '<strong>' . esc_html__( 'Checked:', 'vatnode-eu-vat-rates' ) . '</strong> ' . esc_html(
                    wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp )
                );
            }
        }
        echo '</p></div>';
    }
}
