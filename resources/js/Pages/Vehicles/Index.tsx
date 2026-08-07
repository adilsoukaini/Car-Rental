import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps, Paginated, Vehicle } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import SearchBox from '@/Components/SearchBox';
import FilterBar from '@/Components/FilterBar';
import Breadcrumbs from '@/Components/Breadcrumbs';
import EmptyState from '@/Components/EmptyState';
import Text from '@/Components/Text';
import { LayoutSlot } from '@/layoutComponentRegistry';
import { Car } from 'lucide-react';
import { AvailableFilter, AvailableSort } from '@/types/filters';
import { useMemo, useState } from 'react';

/**
 * Fleet listing with two page-layout variants, swappable by an admin via the
 * Layout Variants page (fleetLayout slot, registered in AppServiceProvider):
 *
 *  - 'fleet-layout-default'  — inline search box + filter bar above the grid
 *  - 'fleet-layout-sidebar'  — sticky search/filter sidebar (md:w-1/4) beside
 *    the grid (md:w-3/4), matching the Stitch "Sidebar Search Layout"
 *
 * Both variants share the exact same search/filter/sort state and client-side
 * filtering logic — only the arrangement of the controls differs. Cards
 * render through LayoutSlot name="vehicleCard", so the admin's card-variant
 * selection (vertical / horizontal-split) applies here too.
 */
export default function Index({ vehicles }: { vehicles: Paginated<Vehicle> }) {
    const [searchQuery, setSearchQuery] = useState('');
    const [activeFilters, setActiveFilters] = useState<Record<string, string | string[]>>({});
    const [activeSort, setActiveSort] = useState('');

    // Which fleet-listing page layout is active, shared from
    // HandleInertiaRequests. Defaults to the inline layout when the
    // layout_settings table doesn't exist yet or no row is set for this slot.
    const { activeLayoutVariants } = usePage<PageProps>().props;
    const fleetLayout = activeLayoutVariants?.fleetLayout ?? 'fleet-layout-default';
    const isSidebarLayout = fleetLayout === 'fleet-layout-sidebar';

    // Derive available filters from the loaded vehicles.
    const availableFilters: AvailableFilter[] = useMemo(() => {
        const categories = [...new Set(vehicles.data.map((v) => v.category).filter(Boolean))].sort();
        return [
            {
                name: 'category',
                label: 'Category',
                type: 'select' as const,
                options: categories.map((c) => ({ value: c, label: c })),
            },
            {
                name: 'transmission',
                label: 'Transmission',
                type: 'select' as const,
                options: [
                    { value: 'automatic', label: 'Automatic' },
                    { value: 'manual', label: 'Manual' },
                ],
            },
        ];
    }, [vehicles.data]);

    const availableSorts: AvailableSort[] = [
        { value: 'price_asc', label: 'Price: Low to High' },
        { value: 'price_desc', label: 'Price: High to Low' },
        { value: 'name_asc', label: 'Name: A–Z' },
    ];

    // Filter and sort client-side.
    const filtered = useMemo(() => {
        let result = [...vehicles.data];

        // Text search by make/model.
        if (searchQuery.trim()) {
            const q = searchQuery.toLowerCase();
            result = result.filter(
                (v) =>
                    v.make.toLowerCase().includes(q) ||
                    v.model.toLowerCase().includes(q),
            );
        }

        // Category filter.
        const categoryFilter = activeFilters['category'];
        if (typeof categoryFilter === 'string' && categoryFilter !== '') {
            result = result.filter((v) => v.category === categoryFilter);
        }

        // Transmission filter.
        const transmissionFilter = activeFilters['transmission'];
        if (typeof transmissionFilter === 'string' && transmissionFilter !== '') {
            result = result.filter(
                (v) => v.transmission_type === transmissionFilter,
            );
        }

        // Sort.
        if (activeSort === 'price_asc') {
            result.sort((a, b) => parseFloat(a.daily_rate) - parseFloat(b.daily_rate));
        } else if (activeSort === 'price_desc') {
            result.sort((a, b) => parseFloat(b.daily_rate) - parseFloat(a.daily_rate));
        } else if (activeSort === 'name_asc') {
            result.sort((a, b) => a.make.localeCompare(b.make) || a.model.localeCompare(b.model));
        }

        return result;
    }, [vehicles.data, searchQuery, activeFilters, activeSort]);

    const clearFilters = () => {
        setSearchQuery('');
        setActiveFilters({});
        setActiveSort('');
    };

    const isFiltered = searchQuery !== '' || activeSort !== '' || Object.values(activeFilters).some(
        (v) => (Array.isArray(v) ? v.length > 0 : v !== ''),
    );

    // Shared FilterBar props — identical for both layouts, only placement
    // differs.
    const filterBarProps = {
        filters: availableFilters,
        sorts: availableSorts,
        activeFilters,
        activeSort,
        onFilterChange: (name: string, value: string | string[]) =>
            setActiveFilters((prev) => ({ ...prev, [name]: value })),
        onSortChange: setActiveSort,
        onClear: clearFilters,
    };

    // Shared results summary, card grid, empty state, and pagination — the
    // same blocks both layouts render, in the same order.
    const resultsSummary =
        filtered.length > 0 ? (
            <p className="mb-4 text-sm text-textMuted">
                Showing {filtered.length} {filtered.length === 1 ? 'vehicle' : 'vehicles'}
            </p>
        ) : null;

    const vehicleGrid =
        filtered.length === 0 ? (
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
                {filtered.map((vehicle) => (
                    <LayoutSlot key={vehicle.id} name="vehicleCard" vehicle={vehicle} />
                ))}
            </div>
        );

    const pagination =
        vehicles.last_page > 1 && !isFiltered ? (
            <nav className="mt-8 flex gap-2">
                {vehicles.links.map((link, i) => (
                    <Link
                        key={i}
                        href={link.url ?? '#'}
                        className={`rounded-interactive px-3 py-1 text-sm ${
                            link.active
                                ? 'bg-primary text-onPrimary'
                                : 'border border-border text-textMuted'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
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
                                    onSearch={setSearchQuery}
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
                                onSearch={setSearchQuery}
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
