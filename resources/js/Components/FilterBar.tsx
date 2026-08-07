import { AvailableFilter, AvailableSort } from '@/types/filters';

interface FilterBarProps {
    filters: AvailableFilter[];
    sorts: AvailableSort[];
    activeFilters: Record<string, string | string[]>;
    activeSort: string;
    onFilterChange: (name: string, value: string | string[]) => void;
    onSortChange: (value: string) => void;
    onClear: () => void;
    className?: string;
}

/**
 * Renders a horizontal row of filter controls (one per `AvailableFilter`) plus
 * a sort dropdown and a "Clear all" reset. Purely presentational — the parent
 * owns the active state and decides what to do with the callbacks (this is
 * frontend-only infrastructure; there is no backend filter system behind it).
 *
 * Styling is entirely theme-token-driven (Hard Rule 3). Wraps on mobile,
 * sits on one row on desktop.
 */
export default function FilterBar({
    filters,
    sorts,
    activeFilters,
    activeSort,
    onFilterChange,
    onSortChange,
    onClear,
    className = '',
}: FilterBarProps) {
    const inputClass =
        'rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none focus:ring-focusRing';

    const hasActiveFilters =
        activeSort !== '' ||
        Object.values(activeFilters).some((value) =>
            Array.isArray(value)
                ? value.some((entry) => entry !== '')
                : value !== '',
        );

    return (
        <div className={`flex flex-wrap items-end gap-3 ${className}`}>
            {filters.map((filter) => {
                const raw = activeFilters[filter.name];
                const isArray = Array.isArray(raw);

                return (
                    <div key={filter.name} className="flex flex-col gap-1">
                        <label
                            htmlFor={`filter-${filter.name}`}
                            className="text-xs font-semibold text-textMuted"
                        >
                            {filter.label}
                        </label>

                        {filter.type === 'select' && (
                            <select
                                id={`filter-${filter.name}`}
                                value={typeof raw === 'string' ? raw : isArray ? (raw[0] ?? '') : ''}
                                onChange={(e) => onFilterChange(filter.name, e.target.value)}
                                className={inputClass}
                            >
                                <option value="">All</option>
                                {filter.options?.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        )}

                        {filter.type === 'checkbox' && (
                            <div className="flex flex-wrap items-center gap-3 py-2">
                                {filter.options?.map((option) => {
                                    const selected =
                                        isArray && raw.includes(option.value);
                                    return (
                                        <label
                                            key={option.value}
                                            className="flex items-center gap-1 text-sm text-text"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selected}
                                                onChange={() => {
                                                    const current = isArray ? raw : [];
                                                    const next = selected
                                                        ? current.filter((v) => v !== option.value)
                                                        : [...current, option.value];
                                                    onFilterChange(filter.name, next);
                                                }}
                                                className="rounded border-border text-primary shadow-sm focus:ring-focusRing"
                                            />
                                            {option.label}
                                        </label>
                                    );
                                })}
                            </div>
                        )}

                        {filter.type === 'range' && (
                            <div className="flex items-center gap-2">
                                <input
                                    type="number"
                                    aria-label={`${filter.label} minimum`}
                                    placeholder="Min"
                                    min={filter.min}
                                    max={filter.max}
                                    value={isArray ? (raw[0] ?? '') : ''}
                                    onChange={(e) =>
                                        onFilterChange(filter.name, [
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
                                    placeholder="Max"
                                    min={filter.min}
                                    max={filter.max}
                                    value={isArray ? (raw[1] ?? '') : ''}
                                    onChange={(e) =>
                                        onFilterChange(filter.name, [
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
                    Sort by
                </label>
                <select
                    id="filter-sort"
                    value={activeSort}
                    onChange={(e) => onSortChange(e.target.value)}
                    className={inputClass}
                >
                    <option value="">Default</option>
                    {sorts.map((sort) => (
                        <option key={sort.value} value={sort.value}>
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
                    Clear all
                </button>
            )}
        </div>
    );
}
