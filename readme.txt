===EU VAT Rates for WooCommerce ===
Contributors: vatnode
Tags: woocommerce, vat, eu, tax, europe
Requires at least: 6.3
Tested up to: 6.7
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 9.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Always up-to-date EU VAT rates for WooCommerce. Synced daily from the European Commission TEDB.

== Description ==

Stop manually updating EU tax rates. **EU VAT Rates for WooCommerce** fetches the official standard VAT rates for all 27 EU member states plus the UK directly from the European Commission's Taxes in Europe Database (TEDB) and keeps your WooCommerce tax table current — automatically, every day.

**Why this plugin?**

Most free VAT rate plugins ship with hard-coded rates that go stale. EU VAT rates change (Estonia raised theirs from 20% to 22% in 2024). An outdated rate means wrong tax charged, which means accounting problems.

This plugin syncs from the same source the European Commission publishes — every 24 hours.

**What it does:**

* Syncs standard EU VAT rates for 28 countries (EU-27 + UK) into your WooCommerce tax table
* Runs automatically every day — no maintenance required
* Shows a clear admin dashboard with last sync time, data version, and all current rates
* Manual "Sync now" button for immediate updates
* Lightweight — no bloat, no external dependencies beyond WooCommerce

**Data source:** [European Commission TEDB](https://taxation-customs.ec.europa.eu/tedb/vatRates.html) via [vatnode/eu-vat-rates-data](https://github.com/vatnode/eu-vat-rates-data)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate through **Plugins → Installed Plugins**
3. Go to **WooCommerce → EU VAT Rates** to verify the sync ran

On activation the plugin syncs immediately and schedules a daily auto-sync.

== Frequently Asked Questions ==

= Which countries are covered? =

All 27 EU member states plus the United Kingdom: AT, BE, BG, CY, CZ, DE, DK, EE, ES, FI, FR, GB, GR, HR, HU, IE, IT, LT, LU, LV, MT, NL, PL, PT, RO, SE, SI, SK.

= Which rates are synced? =

Standard rates only in the first release. Reduced rates are displayed in the admin table for reference.

= What happens if the sync fails? =

The last successful rates remain in WooCommerce. The admin page shows the error. The next automatic daily attempt will retry.

= Does this replace my existing tax rates? =

It upserts (insert or update) rates named "VAT" per country. Rates with different names are untouched.

== Changelog ==

= 1.0.0 =
* Initial release
