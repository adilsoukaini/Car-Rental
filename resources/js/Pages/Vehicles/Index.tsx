import PublicLayout from '@/Layouts/PublicLayout';
import { Paginated, Vehicle } from '@/types';
import { Head, Link } from '@inertiajs/react';
import SearchBox from '@/Components/SearchBox';
import FilterBar from '@/Components/FilterBar';
import Breadcrumbs from '@/Components/Breadcrumbs';
import EmptyState from '@/Components/EmptyState';
import Text from '@/Components/Text';
import { Car } from 'lucide-react';
import { AvailableFilter, AvailableSort } from '@/types/filters';
import { useMemo, useState } from 'react';

export default function Index({ vehicles }: { vehicles: Paginated<Vehicle> }) {
    const [searchQuery, setSearchQuery] = useState('');
    const [activeFilters, setActiveFilters] = useState<Record<string, string | string[]>>({});
    const [activeSort, setActiveSort] = useState('');

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

    return (
        <PublicLayout>
            <Head title="Our Fleet" />

            <div className="mx-auto max-w-7xl p-8">
                <Breadcrumbs items={[{ label: 'Our Fleet' }]} className="mb-4" />

                <Text variant="h1" className="mb-6">
                    Our Fleet
                </Text>

                <div className="mb-6 space-y-4">
                    <SearchBox
                        onSearch={setSearchQuery}
                        placeholder="Search vehicles..."
                        className="max-w-md"
                    />

                    <FilterBar
                        filters={availableFilters}
                        sorts={availableSorts}
                        activeFilters={activeFilters}
                        activeSort={activeSort}
                        onFilterChange={(name, value) =>
                            setActiveFilters((prev) => ({ ...prev, [name]: value }))
                        }
                        onSortChange={setActiveSort}
                        onClear={clearFilters}
                    />
                </div>

                {filtered.length > 0 && (
                    <p className="mb-4 text-sm text-textMuted">
                        Showing {filtered.length} {filtered.length === 1 ? 'vehicle' : 'vehicles'}
                    </p>
                )}

                {filtered.length === 0 ? (
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
                            <Link
                                key={vehicle.id}
                                href={route('vehicles.show', vehicle.id)}
                                className="rounded-container border border-border bg-surface p-6 shadow-resting transition hover:shadow-raised"
                            >
                                <h2 className="mb-1 font-display text-xl font-semibold text-text">
                                    {vehicle.make} {vehicle.model}
                                </h2>
                                <p className="mb-3 text-sm text-textMuted">
                                    {vehicle.year} · {vehicle.category} · {vehicle.seat_count} seats ·{' '}
                                    {vehicle.transmission_type}
                                </p>
                                {vehicle.location && (
                                    <p className="mb-3 text-sm text-textMuted">
                                        {vehicle.location.city}
                                    </p>
                                )}
                                <p className="font-mono text-lg font-semibold text-text">
                                    {vehicle.daily_rate} MAD / day
                                </p>
                            </Link>
                        ))}
                    </div>
                )}

                {vehicles.last_page > 1 && !isFiltered && (
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
                )}
            </div>
        </PublicLayout>
    );
}
