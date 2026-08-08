import { useCurrency } from '@/hooks/useCurrency';
import { useTranslation } from '@/hooks/useTranslation';
import {
    CURRENCY_CODES,
    CURRENCY_LABELS,
    CURRENCY_SYMBOLS,
    CurrencyCode,
} from '@/lib/exchangeRates';
import { ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * Clickable currency selector — a compact badge (e.g. "MAD") that opens a
 * small dropdown of the available display currencies, mirroring the FR|EN
 * locale switcher's lightweight pattern (a plain useState toggle, no Headless
 * UI). Choosing a currency persists the preference to localStorage and every
 * price on the page re-renders in that currency (see hooks/useCurrency.ts).
 *
 * `mode="inline"` is for the authenticated profile menu (dropdown aligned to
 * the right edge); the default mode is for the header / mobile menu. `align`
 * controls which edge the dropdown hangs from — the mobile menu badge sits on
 * the left of a row, the desktop nav badge sits toward the right.
 */
export default function CurrencySelector({
    mode = 'dropdown',
    align = 'right',
}: {
    mode?: 'dropdown' | 'inline';
    align?: 'left' | 'right';
}) {
    const { t } = useTranslation();
    const { currency, changeCurrency } = useCurrency();
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    // Close when clicking outside the control (dropdown mode only — the
    // inline variant lives inside a Headless UI Menu, which manages its own
    // focus/outside-click handling).
    useEffect(() => {
        if (!open || mode !== 'dropdown') {
            return;
        }
        const onPointerDown = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', onPointerDown);
        return () => document.removeEventListener('mousedown', onPointerDown);
    }, [open, mode]);

    const select = (code: CurrencyCode) => {
        changeCurrency(code);
        setOpen(false);
    };

    const options = (
        <ul
            role="listbox"
            aria-label={t('Currency')}
            className="overflow-hidden rounded-interactive bg-surface py-1 shadow-raised ring-1 ring-black ring-opacity-5 focus:outline-none"
        >
            {CURRENCY_CODES.map((code) => (
                <li key={code}>
                    <button
                        type="button"
                        role="option"
                        aria-selected={currency === code}
                        onClick={() => select(code)}
                        className={`flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing ${
                            currency === code ? 'bg-background text-text' : 'text-text hover:bg-background'
                        }`}
                    >
                        <span>
                            <span className="font-semibold">{code}</span>
                            <span className="ml-1.5 text-textMuted">
                                — {t(CURRENCY_LABELS[code])} ({CURRENCY_SYMBOLS[code]})
                                {currency === code ? ` (${t('current')})` : ''}
                            </span>
                        </span>
                    </button>
                </li>
            ))}
        </ul>
    );

    const badge = (
        <button
            type="button"
            onClick={() => setOpen((o) => !o)}
            aria-haspopup="listbox"
            aria-expanded={open}
            aria-label={t('Currency')}
            className="flex items-center gap-1 rounded-pill border border-border px-2 py-1 text-xs font-semibold text-textMuted transition-colors hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
        >
            {currency}
            <ChevronDown
                className={`h-3 w-3 transition-transform ${open ? 'rotate-180' : ''}`}
                aria-hidden="true"
            />
        </button>
    );

    return (
        <div
            ref={mode === 'dropdown' ? containerRef : undefined}
            className="relative"
        >
            {badge}
            {open && (
                <div
                    className={`absolute z-50 mt-1 w-52 ${
                        align === 'left' ? 'left-0' : 'right-0'
                    }`}
                >
                    {options}
                </div>
            )}
        </div>
    );
}
