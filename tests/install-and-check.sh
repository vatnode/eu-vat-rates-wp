#!/bin/sh
# One-shot WordPress smoke test for the vatnode plugin.
# Runs entirely through wp-cli against a SQLite-backed install — no web server.
set -e

cd /w
rm -rf wp && mkdir wp

echo "=== download WordPress ==="
wp --allow-root core download --path=wp --quiet

echo "=== SQLite backend ==="
curl -fsSL -o sqlite.zip https://downloads.wordpress.org/plugin/sqlite-database-integration.zip
unzip -q -o sqlite.zip -d wp/wp-content/plugins
cp wp/wp-content/plugins/sqlite-database-integration/db.copy wp/wp-content/db.php
sed -i "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#/w/wp/wp-content/plugins/sqlite-database-integration#" wp/wp-content/db.php
sed -i "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" wp/wp-content/db.php

echo "=== install ==="
wp --allow-root config create --path=wp --dbname=wordpress --dbuser=root --dbhost=localhost --skip-check --quiet
wp --allow-root config set WP_DEBUG true --raw --path=wp
wp --allow-root config set WP_DEBUG_LOG true --raw --path=wp
wp --allow-root config set WP_DEBUG_DISPLAY true --raw --path=wp
wp --allow-root core install --path=wp --url=http://vatnode.test --title="Test store" \
  --admin_user=admin --admin_password=pw --admin_email=test@example.com --skip-email --quiet

echo "=== WooCommerce ==="
wp --allow-root plugin install woocommerce --activate --path=wp --quiet
wp --allow-root option update woocommerce_default_country "FI:*" --path=wp --quiet
wp --allow-root option update woocommerce_calc_taxes yes --path=wp --quiet

echo "=== plugin under test ==="
cp -r /plugin wp/wp-content/plugins/vatnode-eu-vat-rates
rm -rf wp/wp-content/plugins/vatnode-eu-vat-rates/_build

echo "--- simulate a 1.1.2 install: legacy options must survive the update ---"
wp --allow-root option update euvatr_api_key "vn_live_legacy_key" --path=wp --quiet
wp --allow-root option update euvatr_oss_registered "" --path=wp --quiet
wp --allow-root option update euvatr_last_version "2026-01-01" --path=wp --quiet

wp --allow-root plugin activate vatnode-eu-vat-rates --path=wp
echo "PLUGINS:"
wp --allow-root plugin list --path=wp --field=name --status=active

echo "=== assertions ==="
wp --allow-root eval-file /w/checks.php --path=wp
