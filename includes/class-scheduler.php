<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages the daily WP-Cron event.
 */
class EUVATR_Scheduler {

    const HOOK     = 'euvatr_do_sync';
    const INTERVAL = 'daily';

    public static function init(): void {
        add_action( self::HOOK, [ EUVATR_Sync::class, 'run' ] );
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
            return 'Not scheduled';
        }
        return wp_date( 'Y-m-d H:i:s', $ts ) . ' UTC';
    }
}
