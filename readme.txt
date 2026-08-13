=== vatnode - EU VAT Validation and Reverse Charge ===
Contributors: iuriirogulia
Tags: vat, vies, vat validation, reverse charge, woocommerce
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.1.3
WC requires at least: 8.0
WC tested up to: 11.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Validate EU VAT numbers against VIES at checkout, apply the reverse charge, and keep WooCommerce tax rates in step with the European Commission.

== Description ==

WooCommerce collects a VAT number if you add a field for it, but it does not check the number and it does not know when a sale is zero-rated. This plugin does both, and keeps the EU VAT rate table current while it is at it.

Neither job needs any configuration to get started.

**1. Your tax table stays correct.** The plugin pulls standard VAT rates for every EU member state from the European Commission's Taxes in Europe Database (TEDB) and writes them into the WooCommerce tax table. It re-checks daily, so a rate change lands in your store within 24 hours instead of whenever you happen to read about it.

**2. Your B2B buyers can pay without VAT.** The plugin adds a VAT number field at checkout. The number is checked against its country format offline, stored on the order, and — once you add a vatnode API key — verified live against the official VIES service. When a buyer's VAT number is valid and they are in another EU country, VAT is removed and the reverse charge applies.

= What is free =

* Daily rate sync from the European Commission TEDB for the EU-27 plus XI
* Manual "Sync now" whenever you want it
* VAT number field on both the classic and block checkout
* Offline format checking against the country pattern
* VAT number stored on the order and shown in the admin

= What needs a vatnode API key =

* Live VIES verification of the VAT number
* Automatic reverse charge for verified EU business buyers
* Company name from the official register stored with the order
* VIES consultation number stored as audit evidence, when your vatnode account has a requester VAT configured

Get a key at [vatnode.dev/woocommerce](https://vatnode.dev/woocommerce?ref=woo-plugin) — the free plan includes a monthly request quota. Paste it into **WooCommerce → EU VAT Rates** and save. Nothing else changes.

= OSS and the €10,000 threshold =

If your store is registered for the One Stop Shop, consumers in other EU countries are charged the rate of their own country. That is the default. If you are not registered, untick the OSS box: every EU buyer is then charged your own country's rate, which is what applies while cross-border sales stay under the EU-wide €10,000 threshold.

= How the reverse charge decision is made =

1. Empty field → nothing happens, VAT is charged
2. Wrong format for the country, or a VAT country that does not match the billing country → the shopper is asked to fix it
3. Same country as your store → domestic sale, VAT is charged
4. Another EU country and VIES confirms the number → VAT removed, reverse charge applied
5. VIES says the number does not exist → the shopper is asked to correct it or remove it

If the check cannot be made — no key, quota spent, VIES down — the order still goes through with VAT charged, and the reason is written to the order notes. A checkout is never blocked by an upstream service.

= Data source =

Rates come from the [European Commission TEDB](https://taxation-customs.ec.europa.eu/tedb/vatRates.html). The dataset is open source and also published on GitHub, npm, PyPI, Packagist and RubyGems. A full copy ships inside the plugin, so nothing has to be downloaded for it to work — the daily refresh exists because rates change by law between plugin releases.

== External services ==

The plugin ships with a full copy of the rate dataset and works without contacting anything. Two external services are used on top of that.

**1. eu-vat-rates-data (rate refresh) — keeps the bundled rates current**

VAT rates are set by law and change during the life of a plugin release, so the plugin refreshes its bundled dataset from `https://raw.githubusercontent.com/vatnode/eu-vat-rates-data/main/data/eu-vat-rates-data.json`, hosted by GitHub. This happens once a day and when you click "Sync now". Nothing is downloaded during a page load or a checkout, and if the refresh fails the bundled dataset is used, so the store keeps working. The request sends no personal data: only your site URL as part of the user agent, which GitHub logs as it does for any file download.

GitHub terms: https://docs.github.com/site-policy/github-terms/github-terms-of-service — privacy: https://docs.github.com/site-policy/privacy-policies/github-privacy-statement

**2. vatnode API (VAT number validation) — only if you add an API key**

When you configure a vatnode API key and enable validation, the plugin sends the VAT number entered at checkout to `https://api.vatnode.dev`, which queries the EU Commission's VIES service and returns the result. The request contains the VAT number, your API key and the plugin user agent. It does not contain the customer's name, email, address or order contents. The answer is cached in your site for 24 hours, so entering a VAT number costs one request no matter how often the checkout refreshes.

No requests are made to vatnode until you enter an API key.

vatnode terms: https://vatnode.dev/legal/terms — privacy: https://vatnode.dev/legal/privacy

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it from the WordPress plugin directory
2. Activate it through **Plugins → Installed Plugins**
3. Go to **WooCommerce → EU VAT Rates** — rates sync automatically on first activation
4. Optional: paste a vatnode API key and save — VIES verification and the reverse charge switch on with it

== Frequently Asked Questions ==

= Do I need an API key? =

Only for live VAT number verification and the reverse charge. Rate syncing, the checkout field and format checking work with no key and no account.

= Does it work with the block checkout? =

Yes, both the block checkout and the classic shortcode checkout. On the block checkout the field appears in the contact section, and the total updates as soon as a valid number is entered.

= What happens if VIES is down? =

The order is placed with VAT charged as usual and a note is added to the order explaining that the number could not be verified. Checkout is never blocked.

= How often do EU VAT rates actually change? =

More often than you would expect. Estonia raised its standard rate twice in recent years, Finland moved to 25.5%, and reduced rates change somewhere in the EU most years. Tracking this by hand is not realistic.

= Does this replace my existing tax rates? =

No. The plugin upserts rates named "VAT" per country code — insert if missing, update if already there. Rates with other names, and anything you added manually, are left alone.

= Which countries can be validated? =

VIES covers the 27 EU member states plus XI (Northern Ireland). Rate data covers those plus 17 other European jurisdictions, which are rate-lookup only.

= Where is the VAT number stored? =

On the order, together with the verification status, the registered company name and the VIES consultation number when one was issued. You can see all of it on the order screen in the WooCommerce admin.

= Do Greek EL numbers work? =

Yes. Greece issues VAT numbers under the EL prefix while the billing country is GR, and the plugin treats the two as the same country. An EL number on a Greek billing address validates, and a Greek store selling to a Greek business is correctly treated as domestic.

= Does it work with HPOS? =

Yes. The plugin declares compatibility with High-Performance Order Storage and with the cart and checkout blocks, and reads and writes order data through the WooCommerce CRUD layer either way.

= Can I make the VAT number field mandatory? =

Yes, there is a setting for it. Leave it off unless you sell to businesses only — consumers do not have a VAT number and would not be able to check out.

= Is my API key sent anywhere else? =

No. The key is stored in your own site and travels only to api.vatnode.dev, over HTTPS, in the request that validates a VAT number. It is masked down to its prefix on the settings screen and removed from the database when you uninstall the plugin.

= Where do I get support? =

Open a thread in the support forum for this plugin. Bugs and feature requests are also welcome on GitHub at https://github.com/vatnode/eu-vat-rates-wp.

== Screenshots ==

1. Settings screen — API key, checkout options, sync status and current rates
2. Classic checkout — a verified VAT number removes VAT from the total
3. Block checkout — the same field in the contact section
4. Order screen — VAT number, verification status and registered company name

== Changelog ==

= 1.1.3 =
* The full EU VAT rate dataset now ships inside the plugin, so rates, country patterns and the settings screen work on a site with no outbound network access at all. The daily refresh only replaces it when a newer dataset is published
* Internal names — options, hooks, classes, script handles and the settings page address — now all carry the vatnode prefix, so nothing can collide with another plugin. Existing settings, including a saved API key, are carried over automatically

= 1.1.2 =
* Fixed: Greek VAT numbers were rejected at checkout. Greece issues them under the EL prefix while the billing country is GR, and the two are now treated as one country — a Greek B2B checkout works, and a domestic Greek sale is no longer mistaken for a cross-border one
* Fixed: when the rate source could not be reached and the local cache had expired, every VAT number was reported as invalid and the checkout was blocked. The last known-good dataset is now kept on the site and used instead, and a number that cannot be checked never blocks an order
* Rate refreshes no longer happen inside a shopper's request; a stale cache schedules a background sync and serves the last known-good data meanwhile
* Added Settings and Get an API key links to the plugin row on the Plugins screen
* A newly saved API key is checked immediately, so a typo shows up on the settings screen instead of in the first B2B order
* VIES validation is now on by default, so pasting a key is the only step needed
* Uninstall now also removes the OSS setting and the cached rate dataset

= 1.1.1 =
* Rates are now synced only for the EU-27 plus XI; rates this plugin previously wrote for non-EU countries are removed on the next sync, so an export is no longer taxed at the destination country's rate
* Added an OSS setting: untick it to charge your own country's rate to every EU buyer, as applies below the EU-wide 10,000 EUR threshold

= 1.1.0 =
* Added VAT number field to the classic and block checkout
* Added offline VAT number format checking (no key required)
* Added live VIES validation and automatic reverse charge via a vatnode API key
* Verification result, company name and VIES consultation number stored on the order
* Daily auto-sync is now free for everyone; the paid plugin licence has been removed
* Fixed: synced rates were linked to a country location row, which stopped WooCommerce from applying them at all — existing installs are repaired on the next sync
* Fixed: the last sync time is shown in the site's own date format instead of a raw timestamp
* The stored API key is now masked down to its prefix on screen
* Declared HPOS and cart/checkout blocks compatibility

= 1.0.0 =
* Initial release
* Sync standard EU VAT rates from EC TEDB
* Daily auto-sync via WP-Cron
* Manual sync button
* Admin dashboard with sync status and full rates table
