#!/usr/bin/env bash
# cleanup-artifacts.sh — removes Playwright session artifacts and root-level
# screenshots that accumulate during development/QA.
#
# Run: bash scripts/cleanup-artifacts.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "Cleaning up Playwright artifacts..."

# Remove Playwright MCP session files (.log, .yml snapshots)
if [ -d "$REPO_ROOT/.playwright-mcp" ]; then
    find "$REPO_ROOT/.playwright-mcp" -type f \( -name "*.log" -o -name "*.yml" \) -delete
    echo "  Removed .playwright-mcp/*.log and *.yml files"
fi

# Remove root-level PNG screenshots (match patterns like 01-*, screenshot-*)
shopt -s nullglob
pngs=("$REPO_ROOT"/*.png)
shopt -u nullglob
if [ ${#pngs[@]} -gt 0 ]; then
    rm "${pngs[@]}"
    echo "  Removed ${#pngs[@]} root-level .png files"
else
    echo "  No root-level .png files to remove"
fi

# Remove Playwright test screenshots directory
if [ -d "$REPO_ROOT/.claude/playwright-tests/screenshots" ]; then
    rm -rf "$REPO_ROOT/.claude/playwright-tests/screenshots"
    echo "  Removed playwright test screenshots/"
fi

# Remove .phpunit.result.cache
if [ -f "$REPO_ROOT/.phpunit.result.cache" ]; then
    rm "$REPO_ROOT/.phpunit.result.cache"
    echo "  Removed .phpunit.result.cache"
fi

echo "Done."
