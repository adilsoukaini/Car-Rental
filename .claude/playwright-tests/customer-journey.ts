/**
 * Comprehensive customer-journey E2E test — run against a live dev server at
 * localhost:8099 (Hard Rule 10 / 11 verification standard).
 *
 * Walks the ENTIRE customer journey: homepage → fleet browsing (search /
 * filter / sort) → vehicle detail → checkout → booking tracker → admin panel
 * → mobile responsiveness — with a zero-console-errors assertion after every
 * navigation.
 *
 * Each step is a function that returns `{ pass: boolean, message: string }`.
 * Results are collected into an array and returned. A single failing step
 * does not abort the run — all steps execute so the full picture is visible.
 *
 * Usage: transpile to plain JS and run via Playwright MCP's
 * browser_run_code_unsafe (filename mode), passing a live `page`.
 */

const BASE = 'http://localhost:8099';

type Result = { pass: boolean; message: string };

/**
 * Console-noise filter. The shared Playwright MCP browser has history from
 * other projects (e.g. the e-commerce app on 127.0.0.2:8765), so we only
 * treat console errors that concern our own host as failures.
 */
const isRelevant = (text: string): boolean =>
    !text.includes('127.0.0.2') && !text.includes(':8765');

export async function run(page: any): Promise<Result[]> {
    const results: Result[] = [];
    const errors: string[] = [];

    // ─── Console monitoring — attached FIRST so every navigation below is
    // ─── covered (page.goto reuses the same page object; listeners persist).
    page.on('console', (msg: any) => {
        if (msg.type() === 'error') errors.push(msg.text());
    });
    page.on('pageerror', (err: any) => {
        errors.push('pageerror: ' + String(err));
    });

    // Start from a genuinely logged-out guest session so the storefront
    // steps see guest state (guest checkout fields) and the admin step has
    // to perform a real login. Also pin a desktop viewport so every step
    // (except the mobile one) sees the desktop layouts deterministically.
    await page.context().clearCookies();
    await page.setViewportSize({ width: 1280, height: 900 });

    /** Wrapper: run a step, then assert no relevant console errors appeared
     *  during it. Pushes a { pass, message } entry. Never throws. */
    const check = async (name: string, fn: () => Promise<void>) => {
        const checkpoint = errors.length;
        try {
            await fn();
            await page.waitForTimeout(350); // let late async errors surface
            const fresh = errors.slice(checkpoint).filter(isRelevant);
            if (fresh.length > 0) {
                results.push({
                    pass: false,
                    message: `${name} — console errors: ${fresh.join(' | ')}`,
                });
            } else {
                results.push({ pass: true, message: name });
            }
        } catch (e: any) {
            results.push({ pass: false, message: `${name} — ${e.message}` });
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
    const seeResultSummary = async (text: string | RegExp) => {
        await page.getByText(text).first().waitFor({ state: 'visible', timeout: 10000 });
    };

    // ═══════════════════════════════════════════════════════════════════════
    // 1. Homepage — hero, booking card, featured vehicles, trust section
    // ═══════════════════════════════════════════════════════════════════════
    await check('1. Homepage renders hero + booking card + featured + trust', async () => {
        await page.goto(BASE, { waitUntil: 'domcontentloaded' });

        // Hero
        await seeHeading("L'excellence de la location de voitures.");
        await see('NOUVEAU STANDARD');

        // Booking card (straddles the hero's bottom edge; all links → fleet)
        await see('Lieu de prise en charge');
        await see('Date de prise en charge');
        await see('Trouver un véhicule');

        // Featured vehicles section — card links are absolute
        // (http://localhost:8099/vehicles/{id}), so match on the containing
        // "/vehicles/" path rather than a leading "/vehicles/".
        await seeHeading('Notre sélection de véhicules');
        const featuredCards = await page.locator('a[href*="/vehicles/"]').count();
        if (featuredCards < 1) throw new Error('No featured vehicle cards rendered');

        // Trust / value-props section
        await seeHeading('Pourquoi choisir Project Atlas ?');
        await see('Réservation facile');
        await see('Paiement sécurisé');
        await see('Contrat digital');
        await see('Véhicules récents');
    });

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Fleet browsing — cards, search "Toyota", category filter, price sort
    // ═══════════════════════════════════════════════════════════════════════
    await check('2. Fleet renders cards, search, filter, sort', async () => {
        await page.goto(BASE + '/vehicles', { waitUntil: 'domcontentloaded' });
        await seeHeading('Our Fleet');

        // Cards render — card links are absolute (/vehicles/{id}), so match
        // on the containing path.
        const initialCards = await page.locator('a[href*="/vehicles/"]').count();
        if (initialCards < 1) throw new Error('No vehicle cards on fleet page');
        await see(/Showing \d+ vehicles?/);

        // Search "Toyota" (real UI interaction → debounce → Inertia navigation).
        // Each sub-step starts from a fresh /vehicles so the search / filter /
        // sort states never compound in the URL.
        await page
            .getByRole('searchbox', { name: /search vehicles/i })
            .fill('Toyota');
        await page.waitForTimeout(900); // 200ms debounce + navigation + render
        await seeResultSummary('Showing 1 vehicle');
        await page.getByText('Toyota Corolla').first().waitFor({ state: 'visible' });

        // Filter by category = SUV (real UI interaction)
        await page.goto(BASE + '/vehicles', { waitUntil: 'domcontentloaded' });
        await page
            .getByRole('combobox', { name: 'Category' })
            .selectOption({ label: 'SUV' });
        await page.waitForTimeout(900);
        await seeResultSummary('Showing 4 vehicles');
        const suvCards = await page.locator('a[href*="/vehicles/"]').count();
        if (suvCards !== 4) throw new Error(`Expected 4 SUV cards, got ${suvCards}`);

        // Sort by price ascending (real UI interaction) → cheapest first
        await page.goto(BASE + '/vehicles', { waitUntil: 'domcontentloaded' });
        await page
            .getByRole('combobox', { name: /sort by/i })
            .selectOption({ label: 'Price: Low to High' });
        await page.waitForTimeout(900);
        await page.locator('a[href*="/vehicles/"]').first().waitFor();
        const firstCardText = await page
            .locator('a[href*="/vehicles/"]')
            .first()
            .innerText();
        if (!firstCardText.includes('Kia Picanto')) {
            throw new Error(`Cheapest-first sort failed; first card: ${firstCardText}`);
        }
    });

    // ═══════════════════════════════════════════════════════════════════════
    // 3. Vehicle detail — click a specific vehicle, verify gallery/specs/
    //    included/booking form/recommendations
    // ═══════════════════════════════════════════════════════════════════════
    await check('3. Vehicle detail renders gallery, specs, included, booking form, recommendations', async () => {
        await page.goto(BASE + '/vehicles', { waitUntil: 'domcontentloaded' });

        // Click a specific vehicle card (Toyota Corolla) — card href is
        // absolute, so match on the containing path.
        const toyotaLink = page.locator('a[href*="/vehicles/6"]').first();
        await toyotaLink.waitFor({ state: 'visible' });
        await toyotaLink.click();
        await page.waitForURL('**/vehicles/6');

        await seeHeading('Toyota Corolla (2022)');

        // Gallery (hero image + thumbnail buttons)
        await page
            .locator('img[alt*="Toyota Corolla"]')
            .first()
            .waitFor({ state: 'visible' });
        await page.getByRole('button', { name: /voir l'image/i }).first().waitFor({ state: 'visible' });

        // Specs grid
        await see('5 sièges');
        await see('Automatique');
        await see('Hybride');

        // "Inclus dans le prix"
        await seeHeading('Inclus dans le prix');
        await see('Assurance tous risques');
        await see('Kilométrage illimité');

        // Booking form
        await seeHeading('Réserver');
        await page
            .getByRole('button', { name: 'Continuer la réservation' })
            .first()
            .waitFor({ state: 'visible' });

        // Price
        await see('350 DH / jour');

        // Recommendations widget
        await seeHeading('You might also like');
        const recLinks = await page.locator('a[href*="/vehicles/"]').count();
        if (recLinks < 1) throw new Error('No recommendation cards rendered');
    });

    // ═══════════════════════════════════════════════════════════════════════
    // 4. Checkout — price breakdown in sidebar + guest personal-info form
    // ═══════════════════════════════════════════════════════════════════════
    await check('4. Checkout shows price breakdown + guest info form', async () => {
        await page.goto(
            BASE + '/vehicles/6/book?pickup_at=2026-08-10T10:00&return_at=2026-08-12T10:00',
            { waitUntil: 'domcontentloaded' }
        );
        await page.waitForURL('**/vehicles/6/book**');
        await seeHeading('Informations personnelles');

        // Guest fields visible (we are logged out). getByRole name matching
        // is substring-based by default ("Nom" matches "Prénom"), so require
        // exact accessible-name matches.
        await page
            .getByRole('textbox', { name: 'Prénom', exact: true })
            .waitFor({ state: 'visible' });
        await page
            .getByRole('textbox', { name: 'Nom', exact: true })
            .waitFor({ state: 'visible' });
        await page
            .getByRole('textbox', { name: 'Email', exact: true })
            .waitFor({ state: 'visible' });
        await page
            .getByRole('textbox', { name: 'Téléphone', exact: true })
            .waitFor({ state: 'visible' });

        // Price breakdown in the sidebar
        await seeHeading('Détails du prix');
        await see('350 DH × 2 jours');
        await see('700 DH');
        await see('Caution');
        await see('140 DH');
        await see('Total');
        await page
            .getByRole('button', { name: 'Confirmer et payer' })
            .waitFor({ state: 'visible' });
    });

    // ═══════════════════════════════════════════════════════════════════════
    // 5. Booking tracker — lookup form renders
    // ═══════════════════════════════════════════════════════════════════════
    await check('5. Booking tracker lookup form renders', async () => {
        await page.goto(BASE + '/bookings/track', { waitUntil: 'domcontentloaded' });
        await seeHeading('Find your booking');
        await page
            .getByRole('textbox', { name: 'Booking reference' })
            .waitFor({ state: 'visible' });
        await page.getByRole('textbox', { name: 'Email' }).waitFor({ state: 'visible' });
        await page.getByRole('button', { name: 'Find my booking' }).waitFor({ state: 'visible' });
    });

    // ═══════════════════════════════════════════════════════════════════════
    // 6. Admin panel — login, dashboard, Layout Variants, Plugins
    // ═══════════════════════════════════════════════════════════════════════
    await check('6. Admin login + dashboard loads', async () => {
        await page.goto(BASE + '/admin/login', { waitUntil: 'domcontentloaded' });
        const emailField = page.getByRole('textbox', { name: /email/i });
        if ((await emailField.count()) > 0) {
            await emailField.fill('admin@example.com');
            await page.getByRole('textbox', { name: /password/i }).fill('password');
            await page.getByRole('button', { name: /sign in/i }).click();
        }
        await page.waitForURL('**/admin', { timeout: 15000 });
        await seeHeading('Dashboard');
    });

    await check('7. Admin Layout Variants page renders', async () => {
        await page.goto(BASE + '/admin/layout-settings', { waitUntil: 'domcontentloaded' });
        await seeHeading('Layout Settings');
        await page.getByRole('combobox', { name: /vehiclecard/i }).waitFor({ state: 'visible' });
        await page.getByRole('combobox', { name: /fleetlayout/i }).waitFor({ state: 'visible' });
        await page.getByRole('combobox', { name: /checkoutstyle/i }).waitFor({ state: 'visible' });
        await page.getByRole('button', { name: /save layout/i }).waitFor({ state: 'visible' });
    });

    await check('8. Admin Plugins page lists fleet-management', async () => {
        await page.goto(BASE + '/admin/plugins', { waitUntil: 'domcontentloaded' });
        await seeHeading('Plugins');
        await page.getByText('10 results').first().waitFor({ state: 'visible' });
        await page.getByRole('link', { name: 'fleet-management' }).waitFor({ state: 'visible' });
        await page.getByRole('link', { name: 'booking-engine' }).waitFor({ state: 'visible' });
        await page.getByRole('link', { name: 'payments-stripe' }).waitFor({ state: 'visible' });
    });

    // ═══════════════════════════════════════════════════════════════════════
    // 9. Mobile responsive — 390px viewport, no horizontal scroll anywhere
    // ═══════════════════════════════════════════════════════════════════════
    await check('9. Mobile (390px) renders without horizontal scroll', async () => {
        await page.setViewportSize({ width: 390, height: 844 });

        const noHScroll = async () => {
            const m = await page.evaluate(() => ({
                w: window.innerWidth,
                s: document.documentElement.scrollWidth,
            }));
            if (m.s > m.w) {
                throw new Error(`horizontal scroll: ${m.s} > ${m.w}`);
            }
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

    // ═══════════════════════════════════════════════════════════════════════
    // 10. Zero console errors — global sweep over the whole run
    // ═══════════════════════════════════════════════════════════════════════
    const globalErrors = errors.filter(isRelevant);
    results.push({
        pass: globalErrors.length === 0,
        message:
            globalErrors.length === 0
                ? '10. Zero console errors across the entire journey'
                : `10. Console errors across journey: ${globalErrors.join(' | ')}`,
    });

    // Summary
    const passed = results.filter((r) => r.pass).length;
    results.push({
        pass: passed === results.length,
        message: `SUMMARY: ${passed}/${results.length} checks passed`,
    });

    return results;
}
