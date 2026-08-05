#!/usr/bin/env bash
#
# Repeatable proof that a plugin's routes are genuinely gated by the
# `plugins` DB table, not just present-because-the-provider-is-registered.
#
# PHPUnit cannot cover this: AppServiceProvider::boot() (which calls
# PluginManager::boot()) runs before RefreshDatabase migrates the in-memory
# test DB, so the `plugins` table never exists at boot time during a test
# run. This script exercises the real request lifecycle instead — a fresh
# `php artisan serve` process boot per check, against the persistent dev
# DB — which is the only way to faithfully verify this.
#
# Usage: scripts/verify-plugin-toggle.sh [slug] [path]
#   slug  plugin slug in the `plugins` table (default: fleet-management)
#   path  a route path only that plugin registers (default: /vehicles)
#
# Exit code 0 = disabled->404, enabled->200, disabled->404 all confirmed.
# Restores the plugin to ENABLED at the end regardless of outcome, since
# that's the intended working state for a shipped feature.

set -euo pipefail

PHP=/usr/local/bin/php8.4
SLUG="${1:-fleet-management}"
CHECK_PATH="${2:-/vehicles}"
PORT=8199
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

pass=true

start_server() {
    "$PHP" artisan config:clear > /dev/null 2>&1
    "$PHP" artisan serve --port="$PORT" > /tmp/verify-plugin-toggle.log 2>&1 &
    SERVER_PID=$!
    sleep 2
}

stop_server() {
    kill "$SERVER_PID" > /dev/null 2>&1 || true
    wait "$SERVER_PID" 2>/dev/null || true
}

check_status() {
    local expected="$1"
    local actual
    actual=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:${PORT}${CHECK_PATH}")
    if [ "$actual" = "$expected" ]; then
        echo "  OK: ${CHECK_PATH} -> HTTP ${actual} (expected ${expected})"
    else
        echo "  FAIL: ${CHECK_PATH} -> HTTP ${actual} (expected ${expected})"
        pass=false
    fi
}

set_plugin_state() {
    local state="$1" # activate | deactivate
    "$PHP" artisan tinker --execute="
        use App\Core\Support\PluginManager;
        PluginManager::${state}('${SLUG}');
    " > /dev/null 2>&1
}

echo "=== Step 1: deactivate '${SLUG}', expect 404 on ${CHECK_PATH} ==="
set_plugin_state deactivate
start_server
check_status 404
stop_server

echo "=== Step 2: activate '${SLUG}', expect 200 on ${CHECK_PATH} ==="
set_plugin_state activate
start_server
check_status 200
stop_server

echo "=== Step 3: deactivate '${SLUG}' again, expect 404 on ${CHECK_PATH} ==="
set_plugin_state deactivate
start_server
check_status 404
stop_server

echo "=== Restoring '${SLUG}' to enabled (intended working state) ==="
set_plugin_state activate

if [ "$pass" = true ]; then
    echo "PASS: plugin toggle genuinely gates ${CHECK_PATH}"
    exit 0
else
    echo "FAIL: plugin toggle did not behave as expected — see output above"
    exit 1
fi
