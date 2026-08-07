import { ChangeEvent, KeyboardEvent, useEffect, useRef, useState } from 'react';

interface SearchBoxProps {
    onSearch: (query: string) => void;
    placeholder?: string;
    className?: string;
    /**
     * Initial text for the input. The fleet page keys the SearchBox on the
     * server-provided search value (`key={search}`), so when the URL's search
     * changes — a shared/bookmarked link, or browser back/forward — the
     * component remounts with this value pre-filled. During normal typing the
     * component owns its own input state, so the debounce never fights the
     * parent.
     */
    defaultValue?: string;
}

/**
 * Debounced search input. Emits `onSearch(query)` 200ms after the user stops
 * typing — the parent owns whatever the query is used for (the fleet page
 * turns it into a server-side `router.get()` request with `search` in the URL).
 *
 * Styling is entirely theme-token-driven (Hard Rule 3).
 */
export default function SearchBox({
    onSearch,
    placeholder = 'Search vehicles...',
    className = '',
    defaultValue = '',
}: SearchBoxProps) {
    const [value, setValue] = useState(defaultValue);
    const onSearchRef = useRef(onSearch);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Keep the latest callback without re-arming the debounce timer on every
    // parent render (the debounce effect below only depends on `value`).
    useEffect(() => {
        onSearchRef.current = onSearch;
    });

    // Cancel any pending debounce on unmount.
    useEffect(() => {
        return () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }
        };
    }, []);

    const debouncedSearch = (next: string) => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
        }
        timerRef.current = setTimeout(() => {
            onSearchRef.current(next);
        }, 200);
    };

    const clear = () => {
        setValue('');
        if (timerRef.current) {
            clearTimeout(timerRef.current);
        }
        onSearchRef.current('');
    };

    const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
        const next = e.target.value;
        setValue(next);
        debouncedSearch(next);
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Escape') {
            clear();
        }
    };

    return (
        <div className={`relative ${className}`}>
            <svg
                className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-textMuted"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"
                />
            </svg>

            <input
                type="text"
                role="searchbox"
                aria-label={placeholder}
                value={value}
                onChange={handleChange}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                className="w-full rounded-interactive border border-border bg-surface py-2 pl-9 pr-8 text-text placeholder:text-textMuted focus:border-focusRing focus:outline-none focus:ring-focusRing"
            />

            {value !== '' && (
                <button
                    type="button"
                    onClick={clear}
                    aria-label="Clear search"
                    className="absolute right-2 top-1/2 -translate-y-1/2 rounded-interactive p-1 text-textMuted transition-colors hover:bg-background hover:text-text"
                >
                    <svg
                        className="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            )}
        </div>
    );
}
