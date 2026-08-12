#!/usr/bin/env bash
# transpile-playwright-tests.sh — compile the MCP-runner Playwright test
# scripts (`.claude/playwright-tests/*.ts` that export `run(page)`) into
# plain-JS function-expression files (`.mcp.js`) that the Playwright MCP
# harness can load via `browser_run_code_unsafe` (filename mode), which
# evaluates the file as `(content)(page)`.
#
# Usage: bash scripts/transpile-playwright-tests.sh
#
# After running, load the generated `.mcp.js` files via the MCP harness:
#   browser_run_code_unsafe filename=/home/adil/Car-Rental/.claude/playwright-tests/{name}.mcp.js
#
# `notification-bell.spec.ts` is a real @playwright/test spec and does NOT
# need transpiling — run it directly with:
#   npx playwright test .claude/playwright-tests/notification-bell.spec.ts
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIR="$REPO_ROOT/.claude/playwright-tests"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

FILES=("$DIR"/smoke-test.ts "$DIR"/customer-flow.ts "$DIR"/customer-journey.ts "$DIR"/admin-flow.ts)

"$REPO_ROOT/node_modules/.bin/tsc" "${FILES[@]}" \
    --ignoreConfig --target es2020 --module esnext --removeComments --skipLibCheck \
    --outDir "$TMP"

node - "$TMP" "$DIR" <<'NODE'
const fs = require('fs');
const path = require('path');
const [tmp, dir] = process.argv.slice(2);
const names = ['smoke-test', 'customer-flow', 'customer-journey', 'admin-flow'];
for (const name of names) {
    const src = fs.readFileSync(path.join(tmp, `${name}.js`), 'utf8');
    const m = src.match(/^([\s\S]*?)(?:export\s+)?async function run\(([^)]*)\)\s*\{([\s\S]*)\}\s*$/);
    if (!m) throw new Error(`Could not match run() shape in ${name}.js`);
    const pre = m[1].trim();
    const body = m[3];
    const result = `async (${m[2]}) => {\n${pre ? pre + '\n\n' : ''}${body}\n}`;
    fs.writeFileSync(path.join(dir, `${name}.mcp.js`), result);
    console.log(`WROTE ${name}.mcp.js (${result.length} bytes)`);
}
NODE
