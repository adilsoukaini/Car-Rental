/**
 * Admin-facing E2E test — run against a live dev server at localhost:8099
 * (Hard Rule 10 / 11 verification standard).
 *
 * Logs in to the Filament admin panel as admin@example.com / password, then
 * walks every admin surface that drives the storefront:
 *   Dashboard → Vehicles (list + open edit) → Bookings → Layout Variants
 *   → Site Identity → Homepage Content → Plugins → Fleet Filters → Themes.
 *
 * After every step it asserts zero relevant console errors and captures a
 * screenshot. A failing step does not abort the run — all steps execute so
 * the full picture is visible.
 *
 * Run by transpiling to a plain-JS function expression and loading via
 * Playwright MCP's browser_run_code_unsafe (filename mode), passing the
 * live `page`. The MCP server evaluates the file as `(content)(page)`.
 */
export async function run(page: any) {
  const BASE = 'http://localhost:8099';
  const results: string[] = [];
  const errors: string[] = [];
  const SHOTS = '/home/adil/Car-Rental/.claude/playwright-tests/screenshots';

  // ─── Console monitoring — attach FIRST so every navigation is covered.
  page.on('console', (msg: any) => {
    if (msg.type() === 'error') errors.push(msg.text());
  });
  page.on('pageerror', (err: any) => {
    errors.push('pageerror: ' + String(err));
  });

  // The shared Playwright MCP browser has history from other projects
  // (e.g. the e-commerce app on 127.0.0.2:8765) — only treat console errors
  // that concern our own host as failures.
  const isRelevant = (text: string) =>
    !text.includes('127.0.0.2') && !text.includes(':8765');

  // Force a genuinely fresh admin session so the login step performs a real
  // form login rather than reusing an existing cookie. Pin a desktop viewport.
  await page.context().clearCookies();
  await page.setViewportSize({ width: 1280, height: 900 });

  /** Run a step, assert no relevant console errors appeared during it,
   *  take a screenshot, and push a PASS/FAIL line. Never throws. */
  const check = async (name: string, step: number, fn: () => Promise<void>) => {
    const checkpoint = errors.length;
    try {
      await fn();
      await page.waitForTimeout(350); // let late async errors surface
      await page
        .screenshot({ path: `${SHOTS}/admin-flow-${step}.png`, fullPage: false })
        .catch(() => {});
      const fresh = errors.slice(checkpoint).filter(isRelevant);
      if (fresh.length > 0) {
        results.push(`FAIL Step ${step}: ${name} — console errors: ${fresh.join(' | ')}`);
      } else {
        results.push(`PASS Step ${step}: ${name}`);
      }
    } catch (e) {
      results.push(`FAIL Step ${step}: ${name} — ${(e as Error).message}`);
    }
  };

  /** Assert a piece of text is visible (substring, case-insensitive). */
  const see = async (text: string | RegExp, timeout = 10000) => {
    await page.getByText(text).first().waitFor({ state: 'visible', timeout });
  };

  /** Assert a visible heading (role=heading) exists. */
  const seeHeading = async (text: string | RegExp, timeout = 10000) => {
    await page
      .getByRole('heading', { name: text })
      .first()
      .waitFor({ state: 'visible', timeout });
  };

  // ═══════════════════════════════════════════════════════════════════════
  // 1. Admin login — admin@example.com / password
  // ═══════════════════════════════════════════════════════════════════════
  await check('Admin login via form', 1, async () => {
    await page.goto(BASE + '/admin/login', { waitUntil: 'domcontentloaded' });
    const emailField = page.getByRole('textbox', { name: /email/i });
    if ((await emailField.count()) === 0) {
      throw new Error('Login form not rendered after clearing cookies');
    }
    await emailField.fill('admin@example.com');
    await page.getByRole('textbox', { name: /password/i }).fill('password');
    await page.getByRole('button', { name: /sign in/i }).click();
    await page.waitForURL('**/admin', { timeout: 15000 });
    await seeHeading('Dashboard');
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 2. Dashboard — analytics widgets render
  // ═══════════════════════════════════════════════════════════════════════
  await check('Dashboard widgets render', 2, async () => {
    await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded' });
    await seeHeading('Dashboard');
    await see('Total Booking Value');
    await see('Bookings This Month');
    await see('Avg Booking Value');
    await see('Distinct Customers');
    await see('Booking Volume (last 30 days)');
    await see('Vehicle Utilization (last 30 days)');
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 3. Vehicles — list renders, then open the edit page for one vehicle
  // ═══════════════════════════════════════════════════════════════════════
  await check('Vehicles list renders and edit page opens', 3, async () => {
    await page.goto(BASE + '/admin/vehicles', { waitUntil: 'domcontentloaded' });
    await seeHeading('Vehicles');
    await page.getByRole('table', { name: 'vehicles' }).waitFor({ state: 'visible' });
    await see(/results/);

    // Open the edit page for the first vehicle row.
    await page.locator('a[href*="/admin/vehicles/"]').first().waitFor({ state: 'visible' });
    await page.getByRole('link', { name: 'Edit' }).first().click();
    await page.waitForURL('**/admin/vehicles/*/edit');
    await seeHeading(/Edit Vehicle/);
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 4. Bookings — list renders
  // ═══════════════════════════════════════════════════════════════════════
  await check('Bookings list renders', 4, async () => {
    await page.goto(BASE + '/admin/bookings', { waitUntil: 'domcontentloaded' });
    await seeHeading('Bookings');
    await page.getByRole('table', { name: 'bookings' }).waitFor({ state: 'visible' });
    await see(/result/);
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 5. Layout Variants — all region dropdowns present
  // ═══════════════════════════════════════════════════════════════════════
  await check('Layout Variants dropdowns present', 5, async () => {
    await page.goto(BASE + '/admin/layout-settings', { waitUntil: 'domcontentloaded' });
    await seeHeading('Layout Settings');
    await page.getByRole('combobox', { name: /VehicleCard/i }).waitFor({ state: 'visible' });
    await page.getByRole('combobox', { name: /FleetLayout/i }).waitFor({ state: 'visible' });
    await page.getByRole('combobox', { name: /ReviewDisplay/i }).waitFor({ state: 'visible' });
    await page.getByRole('combobox', { name: /CheckoutStyle/i }).waitFor({ state: 'visible' });
    await page.getByRole('combobox', { name: /Vehicle Gallery/i }).waitFor({ state: 'visible' });
    await page.getByRole('button', { name: /save layout/i }).waitFor({ state: 'visible' });
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 6. Site Identity — form fields present
  // ═══════════════════════════════════════════════════════════════════════
  await check('Site Identity form fields present', 6, async () => {
    await page.goto(BASE + '/admin/site-identity', { waitUntil: 'domcontentloaded' });
    await seeHeading('Site Identity');
    await page.getByRole('textbox', { name: 'Site name' }).waitFor({ state: 'visible' });
    await page.getByText('Primary logo').first().waitFor({ state: 'visible' });
    await page.getByText('Favicon').first().waitFor({ state: 'visible' });
    await page.getByRole('button', { name: /save settings/i }).waitFor({ state: 'visible' });
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 7. Homepage Content — form fields present
  // ═══════════════════════════════════════════════════════════════════════
  await check('Homepage Content form fields present', 7, async () => {
    await page.goto(BASE + '/admin/homepage-content', { waitUntil: 'domcontentloaded' });
    await seeHeading('Homepage Content');
    await page.getByRole('textbox', { name: 'Hero title' }).waitFor({ state: 'visible' });
    await page.getByRole('textbox', { name: 'Hero subtitle' }).waitFor({ state: 'visible' });
    await page.getByRole('textbox', { name: 'Hero CTA text' }).waitFor({ state: 'visible' });
    await page.getByRole('textbox', { name: 'Features title' }).waitFor({ state: 'visible' });
    await page.getByRole('textbox', { name: 'CTA band title' }).waitFor({ state: 'visible' });
    await page.getByRole('button', { name: /save settings/i }).waitFor({ state: 'visible' });
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 8. Plugins — list of 10+ plugins with enable toggles
  // ═══════════════════════════════════════════════════════════════════════
  await check('Plugins list shows 10+ plugins with toggles', 8, async () => {
    await page.goto(BASE + '/admin/plugins', { waitUntil: 'domcontentloaded' });
    await seeHeading('Plugins');
    await page.getByRole('table', { name: 'plugins' }).waitFor({ state: 'visible' });
    await see(/10 results/);
    await page.getByRole('link', { name: 'fleet-management' }).waitFor({ state: 'visible' });
    await page.getByRole('link', { name: 'booking-engine' }).waitFor({ state: 'visible' });
    await page.getByRole('link', { name: 'payments-stripe' }).waitFor({ state: 'visible' });

    const toggles = await page.locator('[role="switch"]').count();
    if (toggles < 10) throw new Error(`Expected 10+ plugin toggles, got ${toggles}`);
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 9. Fleet Filters — enable toggles for filters and sorts present
  // ═══════════════════════════════════════════════════════════════════════
  await check('Fleet Filters toggles present', 9, async () => {
    await page.goto(BASE + '/admin/catalog-controls', { waitUntil: 'domcontentloaded' });
    await seeHeading('Fleet Filters');
    await page.getByRole('switch', { name: /Filter.*Category/i }).waitFor({ state: 'visible' });
    await page.getByRole('switch', { name: /Filter.*Transmission/i }).waitFor({ state: 'visible' });
    await page.getByRole('switch', { name: /Sort.*Price: Low to High/i }).waitFor({ state: 'visible' });
    await page.getByRole('switch', { name: /Sort.*Price: High to Low/i }).waitFor({ state: 'visible' });
    await page.getByRole('switch', { name: /Sort.*Name: A–Z/i }).waitFor({ state: 'visible' });
    await page.getByRole('button', { name: /save settings/i }).waitFor({ state: 'visible' });
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 10. Themes — Default active, Demo Rentals present
  // ═══════════════════════════════════════════════════════════════════════
  await check('Themes: Default active, Demo Rentals present', 10, async () => {
    await page.goto(BASE + '/admin/themes', { waitUntil: 'domcontentloaded' });
    await seeHeading('Themes');
    await page.getByRole('table', { name: 'themes' }).waitFor({ state: 'visible' });
    await see(/2 results/);

    // The "Default" theme row must show Active = Yes.
    const defaultRow = page.locator('tr', { hasText: 'Default' }).first();
    await defaultRow.waitFor({ state: 'visible' });
    const defaultRowText = await defaultRow.innerText();
    if (!defaultRowText.includes('Yes')) {
      throw new Error(`Default theme not active; row: ${defaultRowText}`);
    }

    // The Demo Rentals theme must be present.
    await page.getByText(/Demo Rentals/).first().waitFor({ state: 'visible' });
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 11. Mobile admin — key pages at 390px
  // ═══════════════════════════════════════════════════════════════════════
  await check('Mobile admin — 390px, no horizontal overflow', 11, async () => {
    await page.setViewportSize({ width: 390, height: 844 });

    const noHScroll = async () => {
      const m = await page.evaluate(() => ({
        w: window.innerWidth,
        s: document.documentElement.scrollWidth,
      }));
      if (m.s > m.w) throw new Error(`horizontal scroll: ${m.s} > ${m.w}`);
    };

    for (const url of [
      '/admin',
      '/admin/layout-settings',
      '/admin/plugins',
      '/admin/themes',
    ]) {
      await page.goto(BASE + url, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(300);
      await noHScroll();
    }
  });

  await page.setViewportSize({ width: 1280, height: 900 });

  // Summary
  const passed = results.filter((r) => r.startsWith('PASS')).length;
  results.push(`SUMMARY: ${passed}/${results.length} checks passed`);

  return results.join('\n');
}
