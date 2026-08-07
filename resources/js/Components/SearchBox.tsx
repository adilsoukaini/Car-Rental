import { useTranslation } from '@/hooks/useTranslation';
import { ChangeEvent, KeyboardEvent, useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Car } from 'lucide-react';

interface SearchSuggestion {
    id: number;
    make: string;
    model: string;
    category: string;
    imageUrl: string | null;
}

interface SearchBoxProps {
    /**
     * Full-search submit handler. The fleet page turns this into a server-side
     * `router.get()` with `search` in the URL. Fires 200ms after the user stops
     * typing (the existing live server-side filtering behavior) AND immediately
     * when the user presses Enter with no suggestion highlighted — so typing
     * live-filters the grid while the autocomplete dropdown shows instant
     * suggestions.
     */
    onSearch: (query: string) => void;
    placeholder?: string;
    className?: string;
    /**
     * Initial text for the input. The value is re-synced from this prop
     * whenever the input is NOT focused (a shared/bookmarked link, or browser
     * back/forward) — while the user is typing, the live server-side filter
     * re-renders with a new `search` value and the input is deliberately left
     * alone so focus and the open dropdown are never disturbed.
     */
    defaultValue?: string;
}

/**
 * Debounced search input with an instant autocomplete dropdown.
 *
 * Two behaviors share one 200ms debounce:
 *  1. `onSearch(query)` — the existing live server-side filtering (the fleet
 *     grid updates as you type; the URL reflects the query).
 *  2. A fetch to `search.suggestions` — up to 5 matching available vehicles
 *     (id/make/model/category/imageUrl) rendered as a dropdown below the input.
 *
 * The dropdown supports full keyboard navigation (ArrowUp/ArrowDown to move
 * the highlight, Enter to open the highlighted vehicle — or, with no highlight,
 * to commit the full search — Escape to close), mouse hover/click, and closes
 * on Escape or when focus leaves the input. Clicking a suggestion or pressing
 * Enter on it navigates to the vehicle detail page via Inertia.
 *
 * Styling is entirely theme-token-driven (Hard Rule 3).
 */
export default function SearchBox({
    onSearch,
    placeholder: placeholderProp,
    className = '',
    defaultValue = '',
}: SearchBoxProps) {
    const { t } = useTranslation();
    const placeholder = placeholderProp ?? t('Search vehicles...');
    const [value, setValue] = useState(defaultValue);
    const [suggestions, setSuggestions] = useState<SearchSuggestion[]>([]);
    const [open, setOpen] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);

    const inputRef = useRef<HTMLInputElement>(null);
    const onSearchRef = useRef(onSearch);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    // Keep the latest callback without re-arming the debounce timer on every
    // parent render (the debounce effect below only depends on `value`).
    useEffect(() => {
        onSearchRef.current = onSearch;
    });

    // Cancel any pending debounce + in-flight suggestions fetch on unmount.
    useEffect(() => {
        return () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }
            abortRef.current?.abort();
        };
    }, []);

    // Re-sync from the server-provided value only when the input isn't focused
    // (back/forward, shared links). During typing the live server-side filter
    // re-renders with a new `search` prop — never clobber the input then.
    useEffect(() => {
        if (document.activeElement !== inputRef.current) {
            setValue(defaultValue);
            setSuggestions([]);
            setHighlightedIndex(-1);
            setOpen(false);
        }
    }, [defaultValue]);

    const fetchSuggestions = (raw: string) => {
        abortRef.current?.abort();

        const query = raw.trim();
        if (query.length < 2) {
            setSuggestions([]);
            setHighlightedIndex(-1);
            setOpen(false);
            return;
        }

        const controller = new AbortController();
        abortRef.current = controller;

        fetch(route('search.suggestions', { q: query }), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((res) => {
                if (!res.ok) {
                    throw new Error(`Search suggestions failed: ${res.status}`);
                }
                return res.json() as Promise<SearchSuggestion[]>;
            })
            .then((data) => {
                if (controller.signal.aborted) {
                    return;
                }
                setSuggestions(data);
                setHighlightedIndex(-1);
                // Only open while the user is still interacting with the input —
                // a fetch resolving after a blur should never pop the dropdown.
                setOpen(document.activeElement === inputRef.current);
            })
            .catch(() => {
                if (controller.signal.aborted) {
                    return;
                }
                setSuggestions([]);
                setOpen(false);
            });
    };

    const debouncedChange = (next: string) => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
        }
        timerRef.current = setTimeout(() => {
            onSearchRef.current(next);
            fetchSuggestions(next);
        }, 200);
    };

    const clear = () => {
        abortRef.current?.abort();
        if (timerRef.current) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }
        setValue('');
        setSuggestions([]);
        setHighlightedIndex(-1);
        setOpen(false);
        onSearchRef.current('');
    };

    const navigateTo = (suggestion: SearchSuggestion) => {
        abortRef.current?.abort();
        if (timerRef.current) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }
        setOpen(false);
        router.visit(route('vehicles.show', { vehicle: suggestion.id }));
    };

    // Full search with no highlighted suggestion: flush a still-pending debounce
    // so Enter always commits the current text (if the debounce already fired,
    // the live server-side filter has already applied it — nothing to do).
    const commitFullSearch = () => {
        setOpen(false);
        setHighlightedIndex(-1);
        if (timerRef.current) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
            onSearchRef.current(value);
        }
    };

    const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
        const next = e.target.value;
        setValue(next);
        debouncedChange(next);
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!open && suggestions.length > 0) {
                setOpen(true);
                return;
            }
            setHighlightedIndex((i) => (i >= suggestions.length - 1 ? suggestions.length - 1 : i + 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlightedIndex((i) => (i <= 0 ? -1 : i - 1));
        } else if (e.key === 'Enter') {
            if (open && highlightedIndex >= 0 && suggestions[highlightedIndex]) {
                e.preventDefault();
                navigateTo(suggestions[highlightedIndex]);
            } else {
                commitFullSearch();
            }
        } else if (e.key === 'Escape') {
            if (open) {
                e.preventDefault();
                setHighlightedIndex(-1);
                setOpen(false);
            } else {
                clear();
            }
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
                ref={inputRef}
                type="text"
                role="searchbox"
                aria-label={placeholder}
                aria-autocomplete="list"
                aria-expanded={open}
                aria-controls={open ? 'search-suggestions-listbox' : undefined}
                aria-activedescendant={
                    open && highlightedIndex >= 0 ? `search-suggestion-${highlightedIndex}` : undefined
                }
                value={value}
                onChange={handleChange}
                onKeyDown={handleKeyDown}
                onBlur={() => setOpen(false)}
                placeholder={placeholder}
                className="w-full rounded-interactive border border-border bg-surface py-2 pl-9 pr-8 text-text placeholder:text-textMuted focus:border-focusRing focus:outline-none focus:ring-focusRing"
            />

            {value !== '' && (
                <button
                    type="button"
                    onClick={clear}
                    aria-label={t('Clear search')}
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

            {open && (
                <ul
                    id="search-suggestions-listbox"
                    role="listbox"
                    aria-label={t('Search suggestions')}
                    onMouseDown={(e) => e.preventDefault()}
                    className="absolute left-0 right-0 top-full z-40 mt-2 max-h-80 overflow-auto rounded-container border border-border bg-surface py-1 shadow-raised"
                >
                    {suggestions.length === 0 ? (
                        <li className="px-3 py-2 text-sm text-textMuted">{t('No vehicles found')}</li>
                    ) : (
                        suggestions.map((suggestion, index) => {
                            const isActive = index === highlightedIndex;
                            return (
                                <li
                                    key={suggestion.id}
                                    id={`search-suggestion-${index}`}
                                    role="option"
                                    aria-selected={isActive}
                                >
                                    <Link
                                        href={route('vehicles.show', { vehicle: suggestion.id })}
                                        tabIndex={-1}
                                        onMouseEnter={() => setHighlightedIndex(index)}
                                        className={`flex items-center gap-3 px-3 py-2 transition-colors ${
                                            isActive ? 'bg-background' : ''
                                        }`}
                                    >
                                        {suggestion.imageUrl ? (
                                            <img
                                                src={suggestion.imageUrl}
                                                alt=""
                                                loading="lazy"
                                                className="h-10 w-10 shrink-0 rounded-interactive object-cover"
                                            />
                                        ) : (
                                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-interactive bg-background text-textMuted">
                                                <Car className="h-5 w-5" aria-hidden="true" />
                                            </span>
                                        )}
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-medium text-text">
                                                {suggestion.make} {suggestion.model}
                                            </span>
                                            <span className="block truncate text-xs text-textMuted">
                                                {suggestion.category}
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            );
                        })
                    )}
                </ul>
            )}
        </div>
    );
}
