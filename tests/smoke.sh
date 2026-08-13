#!/bin/sh
# Smoke test: install a throwaway WordPress + WooCommerce and run the plugin
# against it with WP_DEBUG on. Everything happens inside a one-shot container
# through wp-cli on SQLite — no web server, no database server, nothing left
# running afterwards.
#
#   ./tests/smoke.sh
#
# Requires Docker and outbound network (WordPress, WooCommerce and the SQLite
# integration plugin are downloaded on each run).
set -e

PLUGIN_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

# Docker Desktop only shares paths under $HOME, so the work directory cannot
# live in /tmp.
WORK_DIR="${VATNODE_SMOKE_DIR:-$HOME/.cache/vatnode-wptest}"

rm -rf "$WORK_DIR"
mkdir -p "$WORK_DIR"
cp "$PLUGIN_DIR/tests/install-and-check.sh" "$PLUGIN_DIR/tests/checks.php" "$WORK_DIR/"

docker run --rm --user root \
  -v "$WORK_DIR":/w \
  -v "$PLUGIN_DIR":/plugin:ro \
  -w /w \
  --entrypoint sh \
  wordpress:cli \
  -c 'echo "memory_limit=1024M" > /usr/local/etc/php/conf.d/zz-mem.ini && sh /w/install-and-check.sh' \
  2>&1 | tee "$WORK_DIR/smoke.out"

# The verdict is read from the output rather than from an exit code: wp-cli sits
# between the assertions and this shell, and a green run must be proven, not
# assumed from silence.
if grep -q '^RESULT: all checks passed$' "$WORK_DIR/smoke.out"; then
    exit 0
fi

echo
echo "Smoke test FAILED — full output in $WORK_DIR/smoke.out"
exit 1
