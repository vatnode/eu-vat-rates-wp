<?php
/**
 * Assertions run inside a booted WordPress with WooCommerce and the plugin active.
 */

$GLOBALS['failures'] = 0;

function check( string $label, bool $ok, string $detail = '' ): void {
    if ( ! $ok ) {
        $GLOBALS['failures']++;
    }
    echo ( $ok ? '  OK   ' : '  FAIL ' ) . $label . ( $detail !== '' ? " — {$detail}" : '' ) . "\n";
}

echo "--- constants and classes ---\n";
check( 'VATNODE_VERSION defined', defined( 'VATNODE_VERSION' ), defined( 'VATNODE_VERSION' ) ? VATNODE_VERSION : 'missing' );
foreach ( [ 'Vatnode_Data', 'Vatnode_Admin', 'Vatnode_Sync', 'Vatnode_Api', 'Vatnode_Validator', 'Vatnode_Settings' ] as $class ) {
    check( "class {$class}", class_exists( $class ) );
}
check( 'no leftover EUVATR_ class', ! class_exists( 'EUVATR_Data' ) );

echo "--- bundled dataset (works with no network) ---\n";
$bundled = Vatnode_Data::bundled();
check( 'bundled dataset loads', is_array( $bundled ) && ! empty( $bundled['rates'] ) );
check( 'bundled dataset has a version', ! empty( $bundled['version'] ), (string) ( $bundled['version'] ?? '' ) );
check( 'bundled covers 40+ countries', count( $bundled['rates'] ) >= 40, count( $bundled['rates'] ) . ' countries' );
check( 'Finland standard rate is 25.5', abs( (float) $bundled['rates']['FI']['standard'] - 25.5 ) < 0.001, (string) $bundled['rates']['FI']['standard'] );

echo "--- get() never blocks on the network ---\n";
delete_transient( 'vatnode_rates' );
delete_option( 'vatnode_rates_last_good' );
add_filter( 'pre_http_request', function () {
    return new WP_Error( 'blocked', 'outbound HTTP disabled for this test' );
} );
$data = Vatnode_Data::get();
check( 'get() returns data with HTTP blocked', is_array( $data ) && ! empty( $data['rates'] ) );

echo "--- legacy option migration (1.1.2 -> current) ---\n";
check( 'API key carried over', get_option( 'vatnode_api_key' ) === 'vn_live_legacy_key', var_export( get_option( 'vatnode_api_key' ), true ) );
check( 'old API key removed', get_option( 'euvatr_api_key', null ) === null );
check( 'last version carried over', get_option( 'vatnode_last_version' ) === '2026-01-01' );
check( 'migration flag set', get_option( 'vatnode_prefix_migrated' ) === '1' );
check( 'settings see the key', Vatnode_Settings::has_api_key() );

echo "--- cron ---\n";
check( 'daily sync scheduled', (bool) wp_next_scheduled( 'vatnode_do_sync' ) );
check( 'legacy cron hook cleared', ! wp_next_scheduled( 'euvatr_do_sync' ) );

echo "--- admin menu ---\n";
set_current_screen( 'dashboard' );
wp_set_current_user( 1 );
do_action( 'admin_menu' );
global $submenu;
$slugs = array_column( $submenu['woocommerce'] ?? [], 2 );
check( 'settings page registered under WooCommerce', in_array( 'vatnode-settings', $slugs, true ), implode( ', ', $slugs ) );
check( 'no generic eu-vat-rates slug', ! in_array( 'eu-vat-rates', $slugs, true ) );

echo "--- translations ---\n";
foreach ( [ 'de_DE' => 'USt-IdNr.', 'fr_FR' => 'Numéro de TVA', 'pl_PL' => 'Numer VAT', 'ru_RU' => 'НДС-номер', 'el' => 'Αριθμός ΦΠΑ' ] as $locale => $expected ) {
    unload_textdomain( 'vatnode-eu-vat-rates' );
    $mofile = WP_PLUGIN_DIR . "/vatnode-eu-vat-rates/languages/vatnode-eu-vat-rates-{$locale}.mo";
    load_textdomain( 'vatnode-eu-vat-rates', $mofile, $locale );
    $translated = __( 'VAT number', 'vatnode-eu-vat-rates' );
    check( "{$locale} translation applies", $translated === $expected, $translated );
}
unload_textdomain( 'vatnode-eu-vat-rates' );

echo "--- validator decisions (no API key needed) ---\n";
$domestic = Vatnode_Validator::evaluate( 'FI12345671', 'FI' );
check( 'domestic sale is not exempt', empty( $domestic['exempt'] ), wp_json_encode( $domestic ) );
$bad = Vatnode_Validator::evaluate( 'XX123', 'DE' );
check( 'malformed number is rejected', empty( $bad['exempt'] ) && ! empty( $bad['blocking'] ), wp_json_encode( $bad ) );
$mismatch = Vatnode_Validator::evaluate( 'DE123456789', 'FR' );
check( 'country mismatch is rejected', empty( $mismatch['exempt'] ), wp_json_encode( $mismatch ) );

echo "--- rate sync into the WooCommerce tax table ---\n";
remove_all_filters( 'pre_http_request' );
add_filter( 'pre_http_request', function () {
    return new WP_Error( 'blocked', 'outbound HTTP disabled for this test' );
} );
$synced = Vatnode_Sync::run();
check( 'sync completes offline from the bundled data', $synced === true );
global $wpdb;
$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_name = 'VAT'" );
check( 'EU rates written to the tax table', $rows >= 27, "{$rows} rows" );
$fi = $wpdb->get_var( "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country = 'FI' AND tax_rate_name = 'VAT'" );
check( 'Finnish rate stored as 25.5', abs( (float) $fi - 25.5 ) < 0.001, (string) $fi );
$non_eu = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country IN ('NO','CH','GB') AND tax_rate_name = 'VAT'" );
check( 'no rates written for non-EU countries', $non_eu === 0, "{$non_eu} rows" );

echo "\n";
if ( $GLOBALS['failures'] > 0 ) {
    echo "RESULT: {$GLOBALS['failures']} failed\n";
    exit( 1 );
}
echo "RESULT: all checks passed\n";
