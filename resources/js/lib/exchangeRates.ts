/**
 * Display-currency conversion for the storefront.
 *
 * Competitors (DiscoverCars, Kayak, Expedia, Hertz) let visitors view prices
 * in their own currency. The platform's billing currency is MAD (Moroccan
 * Dirham) — every stored price is in MAD — so this is a display-only
 * conversion: the visitor's preferred currency is persisted in localStorage
 * and prices are converted client-side before rendering. The actual charge
 * stays in MAD, surfaced to the customer at checkout via the "Payment in MAD"
 * note.
 *
 * FUTURE-PROOFING (admin-configurable): the rates are deliberately a simple,
 * flat JS object rather than a server round-trip or a hardcoded value spread
 * across components. When an admin settings page is built later, it can write
 * to this same object shape (or, better, to a shared config value the
 * frontend reads at boot) — nothing downstream changes because every consumer
 * goes through convertPrice()/formatCurrency() below. Until then this stays
 * client-side only.
 */

export const CURRENCY_CODES = ['MAD', 'EUR', 'USD'] as const;
export type CurrencyCode = (typeof CURRENCY_CODES)[number];

/** Display names — canonical English keys, localized via useTranslation(). */
export const CURRENCY_LABELS: Record<CurrencyCode, string> = {
    MAD: 'Moroccan Dirham',
    EUR: 'Euro',
    USD: 'US Dollar',
};

/** Currency symbols for the selector + formatter. */
export const CURRENCY_SYMBOLS: Record<CurrencyCode, string> = {
    MAD: 'DH',
    EUR: '€',
    USD: '$',
};

/** Fixed exchange rates — 1 MAD expressed in each currency. */
const RATES: Record<string, number> = {
    MAD: 1, // base
    EUR: 0.092, // 1 MAD = 0.092 EUR (~10.85 MAD/EUR)
    USD: 0.099, // 1 MAD = 0.099 USD (~10.10 MAD/USD)
};

/** localStorage key for the visitor's preferred display currency. */
export const PREFERRED_CURRENCY_KEY = 'preferred-currency';

export function isSupportedCurrency(value: string | null): value is CurrencyCode {
    return value !== null && (CURRENCY_CODES as readonly string[]).includes(value);
}

export function getPreferredCurrency(): CurrencyCode {
    if (typeof window === 'undefined') {
        return 'MAD';
    }
    const stored = window.localStorage.getItem(PREFERRED_CURRENCY_KEY);
    return isSupportedCurrency(stored) ? stored : 'MAD';
}

/**
 * Persist the visitor's preferred currency and notify every component in the
 * current tab (a custom event) and other tabs (the browser `storage` event)
 * so prices re-render without a page reload.
 */
export function setPreferredCurrency(currency: CurrencyCode): void {
    window.localStorage.setItem(PREFERRED_CURRENCY_KEY, currency);
    window.dispatchEvent(new Event('preferred-currency-changed'));
}

export function convertPrice(amountMAD: number, toCurrency: string): number {
    const rate = RATES[toCurrency] ?? 1;
    return amountMAD * rate;
}

export function formatCurrency(amount: number, currency: string): string {
    const symbol = CURRENCY_SYMBOLS[currency as CurrencyCode] ?? 'DH';

    if (currency === 'MAD') {
        return `${amount.toFixed(0)} ${symbol}`;
    }
    return `${symbol}${amount.toFixed(2)}`;
}
