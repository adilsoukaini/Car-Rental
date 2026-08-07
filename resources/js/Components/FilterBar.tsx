import { AvailableFilter, AvailableSort } from '@/types/filters';
import { useTranslation } from '@/hooks/useTranslation';

interface FilterBarProps {
    filters: AvailableFilter[];
    sorts: AvailableSort[];
    activeFilters: Record<string, string | string[]>;
    currentSort: string;
    onFilterChange: (name: string, value: string | string[]) => void;
    onSortChange: (value: string) => void;
    onClear: () => void;
    className?: string;
}

/**
 * Renders a horizontal row of filter controls (one per `AvailableFilter`) plus
 * a sort dropdown and a "Clear all" reset. Purely presentational — the parent
 * owns the active state and decides what to do with the callbacks (the fleet
 * page translates them into server-side `router.get()` requests so filters
 * persist in the URL).
 *
 * Controls are driven by the server's `availableFilters`/`availableSorts`
 * props — a filter provider registered server-side renders automatically with
 * no change here, as long as it uses a `uiType` the renderer already handles.
 *
 * Styling is entirely theme-token-driven (Hard Rule 3). Wraps on mobile,
 * sits on one row on desktop.
 */
export default function FilterBar({
    filters,
    sorts,
    activeFilters,
    currentSort,
    onFilterChange,
    onSortChange,
    onClear,
    className = '',
}: FilterBarProps) {
    const { t } = useTranslation();
    const inputClass =
        'rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none focus:ring-focusRing';

    const hasActiveFilters =
        currentSort !== '' ||
        Object.values(activeFilters).some((value) =>
            Array.isArray(value)
                ? value.some((entry) => entry !== '')
                : value !== '',
        );

    return (
        <div className={`flex flex-wrap items-end gap-3 ${className}`}>
            {filters.map((filter) => {
                const raw = activeFilters[filter.id];
                const isArray = Array.isArray(raw);

                return (
                    <div key={filter.id} className="flex flex-col gap-1">
                        <label
                            htmlFor={`filter-${filter.id}`}
                            className="text-xs font-semibold text-textMuted"
                        >
                            {filter.label}
                        </label>

                        {filter.uiType === 'select' && (
                            <select
                                id={`filter-${filter.id}`}
                                value={typeof raw === 'string' ? raw : isArray ? (raw[0] ?? '') : ''}
                                onChange={(e) => onFilterChange(filter.id, e.target.value)}
                                className={inputClass}
                            >
                                <option value="">{t('All')}</option>
                                {filter.options?.map((option) => (
                                    <option key={String(option.value)} value={String(option.value)}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        )}

                        {filter.uiType === 'checkbox' && (
                            <div className="flex flex-wrap items-center gap-3 py-2">
                                {filter.options?.map((option) => {
                                    const selected =
                                        isArray && raw.includes(String(option.value));
                                    return (
                                        <label
                                            key={String(option.value)}
                                            className="flex items-center gap-1 text-sm text-text"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selected}
                                                onChange={() => {
                                                    const current = isArray ? raw : [];
                                                    const next = selected
                                                        ? current.filter((v) => v !== String(option.value))
                                                        : [...current, String(option.value)];
                                                    onFilterChange(filter.id, next);
                                                }}
                                                className="rounded border-border text-primary shadow-sm focus:ring-focusRing"
                                            />
                                            {option.label}
                                        </label>
                                    );
                                })}
                            </div>
                        )}

                        {filter.uiType === 'range' && (
                            <div className="flex items-center gap-2">
                                <input
                                    type="number"
                                    aria-label={`${filter.label} minimum`}
                                    placeholder={t('Min')}
                                    min={filter.min}
                                    max={filter.max}
                                    value={isArray ? (raw[0] ?? '') : ''}
                                    onChange={(e) =>
                                        onFilterChange(filter.id, [
                                            e.target.value,
                                            isArray ? (raw[1] ?? '') : '',
                                        ])
                                    }
                                    className={`${inputClass} w-24`}
                                />
                                <span className="text-textMuted">–</span>
                                <input
                                    type="number"
                                    aria-label={`${filter.label} maximum`}
                                    placeholder={t('Max')}
                                    min={filter.min}
                                    max={filter.max}
                                    value={isArray ? (raw[1] ?? '') : ''}
                                    onChange={(e) =>
                                        onFilterChange(filter.id, [
                                            isArray ? (raw[0] ?? '') : '',
                                            e.target.value,
                                        ])
                                    }
                                    className={`${inputClass} w-24`}
                                />
                            </div>
                        )}
                    </div>
                );
            })}

            <div className="flex flex-col gap-1">
                <label
                    htmlFor="filter-sort"
                    className="text-xs font-semibold text-textMuted"
                >
                    {t('Sort by')}
                </label>
                <select
                    id="filter-sort"
                    value={currentSort}
                    onChange={(e) => onSortChange(e.target.value)}
                    className={inputClass}
                >
                    <option value="">{t('Default')}</option>
                    {sorts.map((sort) => (
                        <option key={sort.id} value={sort.id}>
                            {sort.label}
                        </option>
                    ))}
                </select>
            </div>

            {hasActiveFilters && (
                <button
                    type="button"
                    onClick={onClear}
                    className="rounded-interactive border border-border bg-surface px-3 py-2 text-sm font-medium text-textMuted transition-colors hover:bg-background hover:text-text"
                >
                    {t('Clear all')}
                </button>
            )}
        </div>
    );
}
