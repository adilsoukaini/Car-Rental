/**
 * Admin flow — thin spec wrapper around admin-flow.ts.
 */
import { test } from '@playwright/test';
import { run } from './admin-flow';

test('admin flow — dashboard + resources', async ({ page }) => {
  const results = await run(page as any);
  const failures = results.split('\n').filter((r) => r.startsWith('FAIL'));
  if (failures.length > 0) {
    throw new Error(failures.join('\n'));
  }
});
