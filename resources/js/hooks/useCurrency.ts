import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { PageProps } from '@/types';
import {
    CurrencyCode,
    PREFERRED_CURRENCY_KEY,
    getPreferredCurrency,
    isSupportedCurrency,
    setPreferredCurrency,
} from '@/lib/exchangeRates';

/**
 * Reactive access to the visitor's preferred display currency.
 *
 * Guests keep their choice in localStorage (per-device). A logged-in user's
 * choice is persisted to the User model (users.metadata.currency, shared to
 * every page as the `currency` Inertia prop) so it survives across sessions
 * and devices — the same split the FR|EN locale switcher draws (guest via
 * URL/local state, logged-in via their account), except currency persists
 * rather than living in the URL.
 *
 * Precedence on load: logged-in user's saved preference > localStorage > MAD.
 * Changing currency writes localStorage immediately (driving the re-render via
 * the custom event below) and, for logged-in users, fires an optimistic POST
 * to /preferences/currency to persist it server-side.
 */
export function useCurrency() {
    const { auth, currency: sharedCurrency } = usePage<
        PageProps & { currency?: string | null }
    >().props;
    const isLoggedIn = Boolean(auth?.user);

    // Resolve the initial value once per mount: the logged-in user's saved
    // preference (shared prop) wins, then the browser's localStorage, then MAD.
    const [currency, setCurrency] = useState<CurrencyCode>(() => {
        if (isLoggedIn && isSupportedCurrency(sharedCurrency ?? null)) {
            return sharedCurrency as CurrencyCode;
        }
        return getPreferredCurrency();
    });

    // Keep localStorage in sync with the server-side preference for logged-in
    // users (e.g. they chose EUR in another browser; this one still says MAD).
    useEffect(() => {
        if (isLoggedIn && isSupportedCurrency(sharedCurrency ?? null)) {
            const saved = sharedCurrency as CurrencyCode;
            if (getPreferredCurrency() !== saved) {
                window.localStorage.setItem(PREFERRED_CURRENCY_KEY, saved);
            }
        }
    }, [isLoggedIn, sharedCurrency]);

    // Re-render prices when the preference changes in this tab (custom event)
    // or in another tab (browser `storage` event).
    useEffect(() => {
        const handler = () => setCurrency(getPreferredCurrency());
        window.addEventListener('preferred-currency-changed', handler);
        window.addEventListener('storage', handler);
        return () => {
            window.removeEventListener('preferred-currency-changed', handler);
            window.removeEventListener('storage', handler);
        };
    }, []);

    const changeCurrency = (code: CurrencyCode) => {
        setPreferredCurrency(code); // localStorage + custom event → immediate UI
        setCurrency(code);
        if (isLoggedIn) {
            // Persist for signed-in users — fire-and-forget; the server
            // returns 204 and the next full page load re-shares the prop.
            router.post(
                route('preferences.currency'),
                { currency: code },
                { preserveState: true, preserveScroll: true, replace: true }
            );
        }
    };

    return { currency, changeCurrency };
}
