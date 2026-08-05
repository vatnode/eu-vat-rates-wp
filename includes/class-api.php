<?php
defined( 'ABSPATH' ) || exit;

/**
 * Client for the vatnode VAT validation API.
 *
 * Every call needs the merchant's own API key (WooCommerce → EU VAT Rates).
 *
 * Answers are cached in the site for 24 hours: entering a VAT number costs one
 * quota request, and every later checkout refresh — up to and including the
 * order itself — reuses that same answer and its consultation number.
 */
class EUVATR_Api {

    const BASE_URL        = 'https://api.vatnode.dev';
    const CACHE_PREFIX    = 'euvatr_vies_';
    const CACHE_HOURS     = 24;
    const TIMEOUT_SECONDS = 8;

    /**
     * Validate a VAT number against VIES.
     *
     * Never throws: transport problems, quota exhaustion and upstream outages
     * all come back as a structured failure so the caller can decide (checkout
     * stays open, admin shows the reason).
     *
     * @return array{ok: bool, valid: bool|null, data: array<string, mixed>|null, error_code: string, message: string, from_cache: bool}
     */
    public static function validate( string $vat_id, bool $use_cache = true ): array {
        $normalized = EUVATR_Format::normalize( $vat_id );

        if ( ! EUVATR_Settings::has_api_key() ) {
            return self::failure( 'NO_API_KEY', __( 'No vatnode API key configured.', 'vatnode-eu-vat-rates' ) );
        }

        $cache_key = self::CACHE_PREFIX . md5( $normalized );

        if ( $use_cache ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        $response = wp_remote_get(
            self::base_url() . '/v1/vat/' . rawurlencode( $normalized ),
            [
                'timeout'    => self::TIMEOUT_SECONDS,
                'user-agent' => self::user_agent(),
                'headers'    => [
                    'Authorization' => 'Bearer ' . EUVATR_Settings::api_key(),
                    'Accept'        => 'application/json',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            EUVATR_Settings::set_key_status( 'unreachable', $response->get_error_message() );
            return self::failure( 'TRANSPORT_ERROR', $response->get_error_message() );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        $body   = is_array( $body ) ? $body : [];

        if ( $status === 200 && isset( $body['valid'] ) ) {
            EUVATR_Settings::set_key_status( 'active' );
            $result = [
                'ok'         => true,
                'valid'      => (bool) $body['valid'],
                'data'       => $body,
                'error_code' => '',
                'message'    => '',
                'from_cache' => false,
            ];
            set_transient( $cache_key, $result, HOUR_IN_SECONDS * self::CACHE_HOURS );
            return $result;
        }

        $code    = (string) ( $body['error']['code'] ?? 'HTTP_' . $status );
        $message = (string) ( $body['error']['message'] ?? sprintf(
            /* translators: %d = HTTP status code */
            __( 'vatnode API returned HTTP %d.', 'vatnode-eu-vat-rates' ),
            $status
        ) );

        // A malformed VAT number says nothing about the key or the upstream, and
        // it is a stable verdict — cache it so a repeated typo costs nothing.
        if ( $code === 'INVALID_FORMAT' ) {
            EUVATR_Settings::set_key_status( 'active' );
            $result = self::failure( $code, $message );
            set_transient( $cache_key, $result, HOUR_IN_SECONDS * self::CACHE_HOURS );
            return $result;
        }

        if ( in_array( $status, [ 401, 403 ], true ) ) {
            EUVATR_Settings::set_key_status( 'invalid', $message );
        } elseif ( $status === 429 ) {
            EUVATR_Settings::set_key_status( 'quota_exceeded', $message );
        } else {
            EUVATR_Settings::set_key_status( 'error', $message );
        }

        return self::failure( $code, $message );
    }

    /**
     * One-off key check for the settings screen.
     *
     * Sends the reserved XX test VAT number: a test key answers with a fixture,
     * a live key answers INVALID_FORMAT. Either way the request never reaches
     * VIES and never consumes quota — only authentication is being probed.
     *
     * @return array{ok: bool, message: string}
     */
    public static function test_key(): array {
        if ( ! EUVATR_Settings::has_api_key() ) {
            return [ 'ok' => false, 'message' => __( 'Enter an API key first.', 'vatnode-eu-vat-rates' ) ];
        }

        $result = self::validate( 'XX0000001', false );

        if ( $result['ok'] || $result['error_code'] === 'INVALID_FORMAT' ) {
            EUVATR_Settings::set_key_status( 'active' );
            return [ 'ok' => true, 'message' => __( 'API key accepted by vatnode.', 'vatnode-eu-vat-rates' ) ];
        }

        if ( in_array( $result['error_code'], [ 'UNAUTHORIZED', 'INVALID_API_KEY', 'ACCOUNT_DELETED', 'HTTP_401', 'HTTP_403' ], true ) ) {
            return [ 'ok' => false, 'message' => __( 'API key was rejected by vatnode.', 'vatnode-eu-vat-rates' ) ];
        }

        return [ 'ok' => false, 'message' => $result['message'] ];
    }

    private static function base_url(): string {
        return untrailingslashit( (string) apply_filters( 'euvatr_api_base_url', self::BASE_URL ) );
    }

    private static function user_agent(): string {
        return 'vatnode-woo/' . EUVATR_VERSION . ' (+https://vatnode.dev)';
    }

    /**
     * @return array{ok: bool, valid: null, data: null, error_code: string, message: string, from_cache: bool}
     */
    private static function failure( string $code, string $message ): array {
        return [
            'ok'         => false,
            'valid'      => null,
            'data'       => null,
            'error_code' => $code,
            'message'    => $message,
            'from_cache' => false,
        ];
    }
}
