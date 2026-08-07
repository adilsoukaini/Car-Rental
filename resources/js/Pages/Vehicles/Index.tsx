import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps, Paginated, Vehicle } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import SearchBox from '@/Components/SearchBox';
import FilterBar from '@/Components/FilterBar';
import Breadcrumbs from '@/Components/Breadcrumbs';
import EmptyState from '@/Components/EmptyState';
import Text from '@/Components/Text';
import { LayoutSlot } from '@/layoutComponentRegistry';
import { Car } from 'lucide-react';
import { AvailableFilter, AvailableSort } from '@/types/filters';

/**
 * Fleet listing with two page-layout variants, swappable by an admin via the
 * Layout Variants page (fleetLayout slot, registered in AppServiceProvider):
 *
 *  - 'fleet-layout-default'  — inline search box + filter bar above the grid
 *  - 'fleet-layout-sidebar'  — sticky search/filter sidebar (md:w-1/4) beside
 *    the grid (md:w-3/4), matching the Stitch "Sidebar Search Layout"
 *
 * Both variants share the exact same server-side filtering: every search/
 * filter/sort change is a `router.get()` to `vehicles.index` with the new
 * query string (`preserveState`, `replace`), so the WHERE/ORDER BY clauses run
 * against the whole fleet server-side — not just the vehicles loaded on the
 * current page. The URL always reflects the active state, so sharing or
 * bookmarking a link preserves it and browser back/forward works. The filter
 * controls render generically from the server-provided `availableFilters`/
 * `availableSorts` props — a filter registered in PHP appears here with zero
 * frontend changes. Cards render through LayoutSlot name="vehicleCard", so the
 * admin's card-variant selection (vertical / horizontal-split) applies too.
 */
interface IndexProps {
    vehicles: Paginated<Vehicle>;
    search: string;
    availableFilters: AvailableFilter[];
    availableSorts: AvailableSort[];
    currentSort: string;
    activeFilters: Record<string, string>;
}

export default function Index({
    vehicles,
    search,
    availableFilters,
    availableSorts,
    currentSort,
    activeFilters,
}: IndexProps) {
    // Which fleet-listing page layout is active, shared from
    // HandleInertiaRequests. Defaults to the inline layout when the
    // layout_settings table doesn't exist yet or no row is set for this slot.
    const { activeLayoutVariants } = usePage<PageProps>().props;
    const fleetLayout = activeLayoutVariants?.fleetLayout ?? 'fleet-layout-default';
    const isSidebarLayout = fleetLayout === 'fleet-layout-sidebar';

    /**
     * The query params currently reflected in the URL. Read live from the
     * address bar (not from props) so a rapid sequence of filter changes —
     * each `router.get()` updates the URL synchronously at visit start —
     * never drops a param that's already been applied but whose response
     * hasn't landed yet.
     */
    const getUrlParams = (): Record<string, string> => {
        const params: Record<string, string> = {};
        const url = new URL(window.location.href);
        url.searchParams.forEach((value, key) => {
            if (value !== '') params[key] = value;
        });
        return params;
    };

    const applyParams = (params: Record<string, string>) => {
        // Drop empty values so the URL stays clean.
        const clean: Record<string, string> = {};
        for (const [key, value] of Object.entries(params)) {
            if (value !== '') clean[key] = value;
        }

        router.get(route('vehicles.index'), clean, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    const handleSearch = (q: string) => {
        applyParams({ ...getUrlParams(), search: q.trim() });
    };

    const handleFilterChange = (name: string, value: string | string[]) => {
        const v = Array.isArray(value) ? value.join(',') : value;
        applyParams({ ...getUrlParams(), [name]: v });
    };

    const handleSortChange = (value: string) => {
        applyParams({ ...getUrlParams(), sort: value });
    };

    const clearFilters = () => {
        applyParams({});
    };

    const isFiltered =
        search !== '' ||
        currentSort !== '' ||
        Object.values(activeFilters).some((v) => v !== '');

    // Shared FilterBar props — identical for both layouts, only placement
    // differs. Values are controlled from the server props so the controls
    // always reflect the URL (back/forward, shared links).
    const filterBarProps = {
        filters: availableFilters,
        sorts: availableSorts,
        activeFilters,
        currentSort,
        onFilterChange: handleFilterChange,
        onSortChange: handleSortChange,
        onClear: clearFilters,
    };

    // Shared results summary, card grid, empty state, and pagination — the
    // same blocks both layouts render, in the same order. `total` is the
    // server-filtered count across all pages.
    const resultsSummary =
        vehicles.total > 0 ? (
            <p className="mb-4 text-sm text-textMuted">
                Showing {vehicles.total} {vehicles.total === 1 ? 'vehicle' : 'vehicles'}
            </p>
        ) : null;

    const vehicleGrid =
        vehicles.data.length === 0 ? (
            <div className="rounded-container border border-border bg-surface">
                <EmptyState
                    icon={<Car className="h-10 w-10" />}
                    title={
                        isFiltered
                            ? 'No vehicles match your search'
                            : 'No vehicles available right now'
                    }
                    description={
                        isFiltered
                            ? 'Try adjusting your filters or search terms.'
                            : 'Check back soon — we update our fleet regularly.'
                    }
                    action={
                        isFiltered ? (
                            <button
                                onClick={clearFilters}
                                className="text-sm font-medium text-primary hover:text-primaryHover"
                            >
                                Clear filters
                            </button>
                        ) : undefined
                    }
                />
            </div>
        ) : (
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {vehicles.data.map((vehicle) => (
                    <LayoutSlot key={vehicle.id} name="vehicleCard" vehicle={vehicle} />
                ))}
            </div>
        );

    // Pagination links carry the query string (paginate()->withQueryString()),
    // so filtering and paging compose. `key` on the SearchBox + defaultValue
    // make the input reflect the URL's search on back/forward and shared links
    // without the page owning the input's typing state.
    const pagination =
        vehicles.last_page > 1 ? (
            <nav className="mt-8 flex gap-2">
                {vehicles.links.map((link, i) =>
                    link.url ? (
                        <Link
                            key={i}
                            href={link.url}
                            className={`rounded-interactive px-3 py-1 text-sm ${
                                link.active
                                    ? 'bg-primary text-onPrimary'
                                    : 'border border-border text-textMuted'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        // Laravel's paginator gives the first/last boundary
                        // links (e.g. "« Previous" on page 1) a null URL —
                        // render them as a disabled element instead of a link
                        // with an empty href (QA finding).
                        <span
                            key={i}
                            aria-disabled="true"
                            className="pointer-events-none rounded-interactive border border-border px-3 py-1 text-sm opacity-50"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    )
                )}
            </nav>
        ) : null;

    return (
        <PublicLayout>
            <Head title="Our Fleet" />

            <div className="mx-auto max-w-7xl p-8">
                <Breadcrumbs items={[{ label: 'Our Fleet' }]} className="mb-4" />

                <Text variant="h1" className="mb-6">
                    Our Fleet
                </Text>

                {isSidebarLayout ? (
                    <div className="flex flex-col gap-8 md:flex-row md:items-start">
                        {/* Sticky sidebar — search + filters in a card, stays
                            visible while scrolling the grid (md:top-24 clears
                            the sticky site header). */}
                        <aside className="w-full shrink-0 md:sticky md:top-24 md:w-1/4">
                            <div className="space-y-6 rounded-container border border-border bg-surface p-5 shadow-resting">
                                <SearchBox
                                    key={search}
                                    defaultValue={search}
                                    onSearch={handleSearch}
                                    placeholder="Search vehicles..."
                                />
                                <FilterBar {...filterBarProps} />
                            </div>
                        </aside>

                        <div className="w-full md:w-3/4">
                            {resultsSummary}
                            {vehicleGrid}
                            {pagination}
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="mb-6 space-y-4">
                            <SearchBox
                                key={search}
                                defaultValue={search}
                                onSearch={handleSearch}
                                placeholder="Search vehicles..."
                                className="max-w-md"
                            />

                            <FilterBar {...filterBarProps} />
                        </div>

                        {resultsSummary}
                        {vehicleGrid}
                        {pagination}
                    </>
                )}
            </div>
        </PublicLayout>
    );
}
