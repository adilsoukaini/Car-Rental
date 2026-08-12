/**
 * Smoke tests — admin↔frontend connections end-to-end (Hard Rule 11).
 * Thin spec wrapper around the MCP-style smoke-test.ts run() function.
 */
import { test } from '@playwright/test';
import { run } from './smoke-test';

test('smoke — admin↔frontend connections', async ({ page }) => {
  const results = await run(page as any);
  const failures = results.split('\n').filter((r) => r.startsWith('FAIL'));
  if (failures.length > 0) {
    throw new Error(failures.join('\n'));
  }
});
