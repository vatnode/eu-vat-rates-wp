===EU VAT Rates for WooCommerce ===
Contributors: vatnode
Tags: woocommerce, vat, eu, tax, europe, tax rates
Requires at least: 6.3
Tested up to: 6.7
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 9.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Always up-to-date EU VAT rates for WooCommerce. Synced daily from the European Commission TEDB — the only official source.

== Description ==

**EU VAT rates change. Most plugins don't.**

Estonia raised its standard VAT from 20% to 22% in January 2024. Stores running stale plugins charged the wrong rate for months. That means wrong taxes collected, wrong figures in accounting, and potential penalties from the tax authority.

**EU VAT Rates for WooCommerce** fixes this permanently. It connects directly to the European Commission's Taxes in Europe Database (TEDB) — the official, authoritative source — and keeps your WooCommerce tax table accurate automatically.

= How it works =

1. Install and activate — rates sync immediately into your WooCommerce tax table
2. Every day the plugin checks for changes and updates automatically (Pro)
3. The admin page shows last sync time, data version, and all current rates

= What's included =

* Standard VAT rates for all 27 EU member states + UK (28 countries)
* Automatic daily sync from the European Commission TEDB (Pro)
* Manual "Sync now" button available at any time
* Clean admin dashboard under WooCommerce → EU VAT Rates
* No bloat — lightweight, no external dependencies beyond WooCommerce

= Why not a free plugin? =

Free VAT rate plugins ship with hard-coded rates that nobody updates. Searching WordPress.org for "EU VAT rates" returns plugins last updated in 2019, 2021, 2022 — all with stale data.

This plugin is a paid service precisely because it's a service: someone (us) is responsible for keeping the data correct. The European Commission updates TEDB, our pipeline detects the change, your store is updated within 24 hours — automatically.

At $39/year, this costs less than one hour of your accountant's time. And wrong VAT rates cost a lot more than that.

= Data source =

All rates are fetched from the [European Commission TEDB](https://taxation-customs.ec.europa.eu/tedb/vatRates.html) via its official SOAP API. The underlying dataset is also available as a free open-source package on [GitHub](https://github.com/vatnode/eu-vat-rates-data), npm, PyPI, and other ecosystems.

= Covered countries =

EU-27 member states + United Kingdom (28 countries):

`AT` `BE` `BG` `CY` `CZ` `DE` `DK` `EE` `ES` `FI` `FR` `GB` `GR` `HR` `HU` `IE` `IT` `LT` `LU` `LV` `MT` `NL` `PL` `PT` `RO` `SE` `SI` `SK`

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate through **Plugins → Installed Plugins**
3. Go to **WooCommerce → EU VAT Rates** — rates sync automatically on first activation
4. Upgrade to Pro for daily auto-sync at [vatnode.dev](https://vatnode.dev)

== Frequently Asked Questions ==

= Do I need WooCommerce? =

Yes. This plugin syncs rates directly into the WooCommerce tax table and requires WooCommerce 8.0 or higher.

= What's the difference between free and Pro? =

The free version lets you sync rates manually by clicking "Sync now". Pro adds daily automatic sync — so your rates stay current even when you forget to check.

= How often do EU VAT rates actually change? =

More often than you'd expect. In 2024 Estonia raised its standard rate. The Netherlands and several other countries adjusted reduced rates. EU VAT law is actively changing with the ViDA (VAT in the Digital Age) reform rolling out 2026–2028. Staying current manually is not realistic.

= What happens if the sync fails? =

The last successfully synced rates remain active in WooCommerce. The admin page displays the error. The next daily attempt retries automatically (Pro).

= Does this replace my existing tax rates? =

The plugin upserts rates named "VAT" per country code (insert if not present, update if already there). Rates with different names or custom rates you've added manually are not touched.

= Is this compatible with EU VAT Number validation plugins? =

Yes — this plugin only manages tax rates. It does not handle VAT number validation. For VIES-based VAT number validation, see [vatnode.dev](https://vatnode.dev).

= Which PHP version is required? =

PHP 8.1 or higher.

== Screenshots ==

1. Admin dashboard showing sync status and current EU VAT rates
2. Free vs Pro — upgrade prompt for auto-sync feature

== Changelog ==

= 1.0.0 =
* Initial release
* Sync standard EU VAT rates for 28 countries from EC TEDB
* Daily auto-sync via WP-Cron (Pro)
* Manual sync button (free)
* Admin dashboard with sync status and full rates table
