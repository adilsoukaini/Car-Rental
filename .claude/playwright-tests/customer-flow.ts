/**
 * Customer-facing E2E test — run against a live dev server at localhost:8099
 * (Hard Rule 10 / 11 verification standard).
 *
 * Walks the PUBLIC storefront journey as a logged-out guest:
 *   homepage → fleet browsing (search / filter / sort) → vehicle detail
 *   → checkout (price breakdown + guest fields) → booking tracker
 *   → mobile (390px) rendering of homepage / fleet / checkout.
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

  // Start from a genuinely logged-out guest session (guest checkout fields)
  // and pin a desktop viewport so every step except the mobile one sees the
  // desktop layouts deterministically.
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
        .screenshot({ path: `${SHOTS}/customer-flow-${step}.png`, fullPage: false })
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

  /** Assert the fleet page currently shows a given "Showing N vehicles"
   *  summary — a reliable proxy for server-side search/filter results. */
  const seeSummary = async (text: string | RegExp) => {
    await page.getByText(text).first().waitFor({ state: 'visible', timeout: 10000 });
  };

  // ═══════════════════════════════════════════════════════════════════════
  // 1. Homepage — hero, booking card, features, CTA band, footer
  // ═══════════════════════════════════════════════════════════════════════
  await check('Homepage renders hero, booking card, features, CTA band, footer', 1, async () => {
    await page.goto(BASE, { waitUntil: 'domcontentloaded' });

    // Hero
    await seeHeading("L'excellence de la location de voitures.");
    await see('NOUVEAU STANDARD');

    // Booking card (straddles the hero's bottom edge; all links → fleet)
    await see('Lieu de prise en charge');
    await see('Date de prise en charge');
    await see('Trouver un véhicule');

    // Features / value-props section
    await seeHeading('Pourquoi choisir Project Atlas ?');
    await see('Réservation facile');
    await see('Paiement sécurisé');
    await see('Contrat digital');
    await see('Véhicules récents');

    // CTA band
    await seeHeading("Prêt pour l'aventure ?");

    // Footer
    await see('Your trusted partner for premium, hassle-free mobility.');
    await see('Track your booking');
    await see(/Car Rental\. All rights reserved\./);
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 2. Fleet browsing — search "Toyota", category filter "SUV", sort price_asc
  // ═══════════════════════════════════════════════════════════════════════
  await check('Fleet search, filter, and sort work', 2, async () => {
    await page.goto(BASE + '/vehicles', { waitUntil: 'domcontentloaded' });
    await seeHeading('Our Fleet');
    await see(/Showing \d+ vehicles?/);

    // Search "Toyota" (real UI interaction → debounce → Inertia navigation).
    // Each sub-step starts from a fresh /vehicles so search/filter/sort
    // states never compound in the URL.
    await page.getByRole('searchbox', { name: /search vehicles/i }).fill('Toyota');
    await page.waitForTimeout(900); // 200ms debounce + navigation + render
    await seeSummary('Showing 1 vehicle');
    await page.getByText('Toyota Corolla').first().waitFor({ state: 'visible' });

    // Filter by category = SUV
    await page.goto(BASE + '/vehicles', { waitUntil: 'domcontentloaded' });
    await page.getByRole('combobox', { name: 'Category' }).selectOption({ label: 'SUV' });
    await page.waitForTimeout(900);
    await seeSummary('Showing 4 vehicles');
    const suvCards = await page.locator('a[href*="/vehicles/"]').count();
    if (suvCards !== 4) throw new Error(`Expected 4 SUV cards, got ${suvCards}`);

    // Sort by price ascending → cheapest first (Kia Picanto, 200 DH)
    await page.goto(BASE + '/vehicles', { waitUntil: 'domcontentloaded' });
    await page.getByRole('combobox', { name: /sort by/i }).selectOption({ label: 'Price: Low to High' });
    await page.waitForTimeout(900);
    const firstCardText = await page.locator('a[href*="/vehicles/"]').first().innerText();
    if (!firstCardText.includes('Kia Picanto')) {
      throw new Error(`Cheapest-first sort failed; first card: ${firstCardText}`);
    }
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 3. Vehicle detail — click a card, verify gallery/specs/included/price/form
  // ═══════════════════════════════════════════════════════════════════════
  await check('Vehicle detail shows gallery, specs, included, price, booking form', 3, async () => {
    await page.goto(BASE + '/vehicles', { waitUntil: 'domcontentloaded' });

    // Click a specific vehicle card (Toyota Corolla, id 6)
    const toyotaLink = page.locator('a[href*="/vehicles/6"]').first();
    await toyotaLink.waitFor({ state: 'visible' });
    await toyotaLink.click();
    await page.waitForURL('**/vehicles/6');

    await seeHeading('Toyota Corolla (2022)');

    // Gallery (hero image + thumbnail buttons)
    await page.locator('img[alt*="Toyota Corolla"]').first().waitFor({ state: 'visible' });
    await page.getByRole('button', { name: /voir l'image/i }).first().waitFor({ state: 'visible' });

    // Specs grid
    await see('5 sièges');
    await see('Automatique');
    await see('Hybride');

    // "Inclus dans le prix"
    await seeHeading('Inclus dans le prix');
    await see('Assurance tous risques');
    await see('Kilométrage illimité');

    // Price + booking form
    await see('350 DH / jour');
    await seeHeading('Réserver');
    await page.getByRole('button', { name: 'Continuer la réservation' }).first().waitFor({ state: 'visible' });
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 4. Checkout — vehicle name, price breakdown, date display, guest fields
  // ═══════════════════════════════════════════════════════════════════════
  await check('Checkout shows vehicle, price breakdown, dates, guest fields', 4, async () => {
    await page.goto(
      BASE + '/vehicles/6/book?pickup_at=2026-08-10T10:00&return_at=2026-08-12T10:00',
      { waitUntil: 'domcontentloaded' }
    );
    await page.waitForURL('**/vehicles/6/book**');
    await seeHeading('Informations personnelles');

    // Vehicle name in the summary sidebar
    await seeHeading('Toyota Corolla');

    // Date display (French locale, formatted in the sidebar)
    await see('lun. 10 août 2026, 10:00');
    await see('mer. 12 août 2026, 10:00');

    // Price breakdown
    await seeHeading('Détails du prix');
    await see('350 DH × 2 jours');
    await see('700 DH');
    await see('Caution');
    await see('140 DH');
    await see('Total');

    // Guest fields visible (we are logged out). getByRole name matching is
    // substring-based by default, so require exact accessible-name matches.
    await page.getByRole('textbox', { name: 'Prénom', exact: true }).waitFor({ state: 'visible' });
    await page.getByRole('textbox', { name: 'Nom', exact: true }).waitFor({ state: 'visible' });
    await page.getByRole('textbox', { name: 'Email', exact: true }).waitFor({ state: 'visible' });
    await page.getByRole('textbox', { name: 'Téléphone', exact: true }).waitFor({ state: 'visible' });
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 5. Booking tracker — lookup form renders
  // ═══════════════════════════════════════════════════════════════════════
  await check('Booking tracker lookup form renders', 5, async () => {
    await page.goto(BASE + '/bookings/track', { waitUntil: 'domcontentloaded' });
    await seeHeading('Find your booking');
    await page.getByRole('textbox', { name: 'Booking reference' }).waitFor({ state: 'visible' });
    await page.getByRole('textbox', { name: 'Email' }).waitFor({ state: 'visible' });
    await page.getByRole('button', { name: 'Find my booking' }).waitFor({ state: 'visible' });
  });

  // ═══════════════════════════════════════════════════════════════════════
  // 6. Mobile responsive — 390px viewport, homepage / fleet / checkout render
  // ═══════════════════════════════════════════════════════════════════════
  await check('Mobile (390px) renders homepage, fleet, and checkout', 6, async () => {
    await page.setViewportSize({ width: 390, height: 844 });

    const noHScroll = async () => {
      const m = await page.evaluate(() => ({
        w: window.innerWidth,
        s: document.documentElement.scrollWidth,
      }));
      if (m.s > m.w) throw new Error(`horizontal scroll: ${m.s} > ${m.w}`);
    };

    // Homepage
    await page.goto(BASE, { waitUntil: 'domcontentloaded' });
    await seeHeading("L'excellence de la location de voitures.");
    await noHScroll();

    // Fleet
    await page.goto(BASE + '/vehicles', { waitUntil: 'domcontentloaded' });
    await seeHeading('Our Fleet');
    await noHScroll();

    // Checkout
    await page.goto(
      BASE + '/vehicles/6/book?pickup_at=2026-08-10T10:00&return_at=2026-08-12T10:00',
      { waitUntil: 'domcontentloaded' }
    );
    await seeHeading('Informations personnelles');
    await noHScroll();
  });

  // Summary
  const passed = results.filter((r) => r.startsWith('PASS')).length;
  results.push(`SUMMARY: ${passed}/${results.length} checks passed`);

  return results.join('\n');
}
