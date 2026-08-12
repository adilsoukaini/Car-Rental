/**
 * Customer-facing E2E test — thin spec wrapper around customer-flow.ts.
 * Runs the public storefront journey (homepage → fleet → detail → checkout → tracker → mobile).
 */
import { test } from '@playwright/test';
import { run } from './customer-flow';

test('customer flow — full storefront journey', async ({ page }) => {
  const results = await run(page as any);
  const failures = results.split('\n').filter((r) => r.startsWith('FAIL'));
  if (failures.length > 0) {
    throw new Error(failures.join('\n'));
  }
});
