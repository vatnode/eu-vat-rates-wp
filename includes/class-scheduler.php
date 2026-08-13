<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages the daily WP-Cron event.
 */
class Vatnode_Scheduler {

    const HOOK     = 'vatnode_do_sync';
    const INTERVAL = 'daily';

    public static function init(): void {
        add_action( self::HOOK, [ Vatnode_Sync::class, 'run' ] );
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            // Schedule first run at next midnight UTC
            $midnight = strtotime( 'tomorrow midnight' );
            wp_schedule_event( $midnight, self::INTERVAL, self::HOOK );
        }
    }

    public static function unschedule(): void {
        $timestamp = wp_next_scheduled( self::HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK );
        }
    }

    public static function next_run(): string {
        $ts = wp_next_scheduled( self::HOOK );
        if ( ! $ts ) {
            return __( 'Not scheduled', 'vatnode-eu-vat-rates' );
        }
        return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
    }
}
