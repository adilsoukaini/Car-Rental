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

# Remove root-level image screenshots (PNG, JPEG, WebP, etc.)
shopt -s nullglob
imgs=("$REPO_ROOT"/*.png "$REPO_ROOT"/*.jpg "$REPO_ROOT"/*.jpeg "$REPO_ROOT"/*.webp "$REPO_ROOT"/*.gif)
shopt -u nullglob
if [ ${#imgs[@]} -gt 0 ]; then
    rm "${imgs[@]}"
    echo "  Removed ${#imgs[@]} root-level image files (.png/.jpg/.jpeg/.webp/.gif)"
else
    echo "  No root-level image files to remove"
fi

# Remove Playwright MCP screenshots/storage
if [ -d "$REPO_ROOT/.playwright-mcp" ]; then
    find "$REPO_ROOT/.playwright-mcp" -type f \( -name "*.png" -o -name "*.jpg" -o -name "*.jpeg" -o -name "*.webp" \) -delete
    echo "  Removed Playwright MCP screenshots"
    # Also remove the entire .playwright-mcp dir if empty
    find "$REPO_ROOT/.playwright-mcp" -type d -empty -delete 2>/dev/null || true
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
