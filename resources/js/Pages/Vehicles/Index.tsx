import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps, Paginated, Vehicle } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';
import { Head, Link, router, usePage } from '@inertiajs/react';
import SearchBox from '@/Components/SearchBox';
import FilterBar from '@/Components/FilterBar';
import Breadcrumbs from '@/Components/Breadcrumbs';
import EmptyState from '@/Components/EmptyState';
import Skeleton from '@/Components/Skeleton';
import Text from '@/Components/Text';
import { LayoutSlot } from '@/layoutComponentRegistry';
import { Car } from 'lucide-react';
import { AvailableFilter, AvailableSort } from '@/types/filters';
import { useEffect, useState } from 'react';

/**
 * Clean URLs — Option A. The free-text search query is truncated to this length
 * before it is written into the URL, so a shared/bookmarked fleet link never
 * carries an arbitrarily long `?search=` value. Other params (category, sort,
 * transmission, ...) are short enum values and pass through untouched.
 */
const MAX_SEARCH_LENGTH = 50;

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
    const { t } = useTranslation();

    // Loading state for the card grid. Inertia swaps the whole page during a
    // full navigation (covered by its built-in top progress bar, configured in
    // app.tsx), but this page's filter/sort/date navigations use preserveState,
    // which keeps this component mounted — so we subscribe to Inertia's
    // start/finish events and swap the grid for Skeleton.Card placeholders
    // while the server round-trip is in flight. Without this, the stale grid
    // lingers and is easily mistaken for the new results.
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        const offStart = router.on('start', () => setIsLoading(true));
        const offFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            offStart();
            offFinish();
        };
    }, []);

    // Date bar state — initialized from the URL so a homepage search with
    // ?pickup=...&return=... pre-fills here (date-only YYYY-MM-DD values,
    // matching the homepage's type="date" inputs). The Update button writes
    // them back into the URL, where they persist alongside search/filters and
    // get carried through to the vehicle detail page by the card links.
    const [pickupDate, setPickupDate] = useState(
        () => new URLSearchParams(window.location.search).get('pickup') ?? '',
    );
    const [returnDate, setReturnDate] = useState(
        () => new URLSearchParams(window.location.search).get('return') ?? '',
    );
    // 30-min time pickers (GAP-1), threaded through the URL as
    // ?pickup_time=...&return_time=... alongside the date-only values, and
    // carried to the detail page by the card links as ?pickup_at=T... . They
    // pre-fill from a homepage search; default 10:00/11:00 like DiscoverCars.
    const [pickupTime, setPickupTime] = useState(
        () => new URLSearchParams(window.location.search).get('pickup_time') ?? '10:00',
    );
    const [returnTime, setReturnTime] = useState(
        () => new URLSearchParams(window.location.search).get('return_time') ?? '11:00',
    );
    const today = new Date().toISOString().slice(0, 10);

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
        // Drop empty values so the URL stays clean, and truncate the search
        // query to MAX_SEARCH_LENGTH before it reaches the URL (Clean URLs,
        // Option A). This single choke point also truncates a long `search`
        // already sitting in the address bar — e.g. a hand-crafted bookmarked
        // link — the moment any filter/sort change rewrites the URL.
        const clean: Record<string, string> = {};
        for (const [key, value] of Object.entries(params)) {
            if (value === '') continue;
            clean[key] = key === 'search' ? value.slice(0, MAX_SEARCH_LENGTH) : value;
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

    const handleDateUpdate = () => {
        // Only persist a valid range — both dates present and return on/after
        // pickup. An invalid combo is simply not written to the URL. Times are
        // carried alongside (only when the matching date is set), so the
        // vehicle-detail booking form pre-fills pickup/return times too.
        if (!pickupDate || !returnDate || returnDate < pickupDate) return;
        applyParams({
            ...getUrlParams(),
            pickup: pickupDate,
            return: returnDate,
            ...(pickupTime ? { pickup_time: pickupTime } : {}),
            ...(returnTime ? { return_time: returnTime } : {}),
        });
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

    // Date bar — pickup/return pickers + an Update button, shared by both
    // layouts (it wraps onto its own rows in the narrow sidebar). Rendered
    // above the search/filter controls. Dates persist in the URL as
    // `?pickup=...&return=...`; the vehicle cards carry them through to the
    // detail page as ?pickup_at=...&return_at=... when the user clicks one.
    const dateBar = (
        <div className="flex flex-wrap items-end gap-3">
            <div className="flex flex-col gap-1">
                <label htmlFor="fleet-pickup" className="text-xs font-semibold text-textMuted">
                    {t('Pickup date')}
                </label>
                <div className="flex gap-2">
                    <input
                        id="fleet-pickup"
                        type="date"
                        value={pickupDate}
                        min={today}
                        onChange={(e) => setPickupDate(e.target.value)}
                        className="rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none focus:ring-focusRing"
                    />
                    <input
                        id="fleet-pickup-time"
                        type="time"
                        value={pickupTime}
                        onChange={(e) => setPickupTime(e.target.value)}
                        aria-label={t('Pickup time')}
                        className="rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none focus:ring-focusRing"
                    />
                </div>
            </div>
            <div className="flex flex-col gap-1">
                <label htmlFor="fleet-return" className="text-xs font-semibold text-textMuted">
                    {t('Return date')}
                </label>
                <div className="flex gap-2">
                    <input
                        id="fleet-return"
                        type="date"
                        value={returnDate}
                        min={pickupDate || today}
                        onChange={(e) => setReturnDate(e.target.value)}
                        className="rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none focus:ring-focusRing"
                    />
                    <input
                        id="fleet-return-time"
                        type="time"
                        value={returnTime}
                        onChange={(e) => setReturnTime(e.target.value)}
                        aria-label={t('Return time')}
                        className="rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none focus:ring-focusRing"
                    />
                </div>
            </div>
            <button
                type="button"
                onClick={handleDateUpdate}
                className="rounded-interactive bg-primary px-4 py-2 text-sm font-semibold text-onPrimary transition-colors hover:bg-primaryHover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
            >
                {t('Update')}
            </button>
        </div>
    );

    // Shared results summary, card grid, empty state, and pagination — the
    // same blocks both layouts render, in the same order. The summary shows a
    // range — "1–12 sur 20 véhicules" — not a bare total, so the user always
    // knows which slice of the result set the current page shows. `from`/`to`
    // come from Laravel's paginator (null on an empty page).
    const resultsSummary =
        vehicles.total > 0 && vehicles.from != null && vehicles.to != null ? (
            <p className="mb-4 text-sm text-textMuted">
                {vehicles.from}–{vehicles.to} {t('of')} {vehicles.total}{' '}
                {vehicles.total === 1 ? t('vehicle') : t('vehicles')}
            </p>
        ) : null;

    const vehicleGrid = isLoading ? (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" aria-busy="true">
            {Array.from({ length: 6 }, (_, i) => (
                <div
                    key={i}
                    className="flex flex-col overflow-hidden rounded-container border border-border bg-surface"
                >
                    <Skeleton.Card />
                    <div className="space-y-3 p-4">
                        <Skeleton.Text className="w-1/3" />
                        <Skeleton.Title />
                        <Skeleton.Text className="w-2/3" />
                    </div>
                </div>
            ))}
        </div>
    ) : vehicles.data.length === 0 ? (
        <div className="rounded-container border border-border bg-surface">
            <EmptyState
                icon={<Car className="h-10 w-10" />}
                title={
                    isFiltered
                        ? t('No vehicles match your search')
                        : t('No vehicles available right now')
                }
                description={
                    isFiltered
                        ? t('Try adjusting your filters or search terms.')
                        : t('Check back soon — we update our fleet regularly.')
                }
                action={
                    isFiltered ? (
                        <button
                            onClick={clearFilters}
                            className="text-sm font-medium text-primary hover:text-primaryHover"
                        >
                            {t('Clear filters')}
                        </button>
                    ) : undefined
                }
            />
        </div>
    ) : (
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {vehicles.data.map((vehicle) => (
                    <LayoutSlot key={vehicle.id} name="vehicleCard" vehicle={vehicle} headingLevel="h2" />
                ))}
            </div>
        );

    // Pagination links carry the query string (paginate()->withQueryString()),
    // so filtering and paging compose. SearchBox re-syncs its value from the
    // server-provided `search` prop whenever the input isn't focused (back/
    // forward, shared links), so the page doesn't own the input's typing state
    // and the live server-side filter never clobbers what's being typed.
    const pagination =
        vehicles.last_page > 1 ? (
            <nav className="mt-8 flex gap-2">
                {vehicles.links.map((link, i) =>
                    link.url ? (
                        <Link
                            key={i}
                            href={link.url}
                            className={`rounded-interactive px-3 py-1 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing ${
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
            <Head title={t('Our Fleet')} />

            <div className="mx-auto max-w-7xl p-8">
                <Breadcrumbs items={[{ label: t('Our Fleet') }]} className="mb-4" />

                <Text variant="h1" className="mb-6">
                    {t('Our Fleet')}
                </Text>

                {isSidebarLayout ? (
                    <div className="flex flex-col gap-8 md:flex-row md:items-start">
                        {/* Sticky sidebar — search + filters in a card, stays
                            visible while scrolling the grid (md:top-24 clears
                            the sticky site header). */}
                        <aside className="w-full shrink-0 md:sticky md:top-24 md:w-1/4">
                            <div className="space-y-6 rounded-container border border-border bg-surface p-5 shadow-resting">
                                {dateBar}
                                <SearchBox
                                    defaultValue={search}
                                    onSearch={handleSearch}
                                    placeholder={t('Search vehicles...')}
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
                            {dateBar}

                            <SearchBox
                                defaultValue={search}
                                onSearch={handleSearch}
                                placeholder={t('Search vehicles...')}
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
