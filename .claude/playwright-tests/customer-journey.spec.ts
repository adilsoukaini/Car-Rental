/**
 * Customer journey (full) — thin spec wrapper around customer-journey.ts.
 * run() returns Result[] ({ pass, message }) — fail the test if any pass=false.
 */
import { test } from '@playwright/test';
import { run } from './customer-journey';

test('customer journey — full regression', async ({ page }) => {
  const results = await run(page as any);
  const failures = results.filter((r) => !r.pass).map((r) => r.message);
  if (failures.length > 0) {
    throw new Error(failures.join('\n'));
  }
});
