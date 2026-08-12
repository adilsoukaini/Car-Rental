import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for the CI/CD frontend tests.
 *
 * The webServer block starts a Laravel dev server on :8099 (matching the
 * BASE constant hardcoded in the .claude/playwright-tests scripts). The
 * scripts were written for the Playwright MCP harness but are now wrapped
 * by thin *.spec.ts files so they run in GitHub Actions via npx playwright test.
 */
export default defineConfig({
  testDir: '.claude/playwright-tests',
  testMatch: '**/*.spec.ts',
  testIgnore: '**/transpile-*.js',
  timeout: 120000,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : [['list']],
  use: {
    baseURL: 'http://localhost:8099',
    headless: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8099',
    url: 'http://localhost:8099/health',
    reuseExistingServer: !process.env.CI,
    timeout: 180000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
