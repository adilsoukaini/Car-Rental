import PublicLayout from '@/Layouts/PublicLayout';
import { SlotOutlet } from '@/pluginComponentRegistry';
import { Vehicle } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import Breadcrumbs from '@/Components/Breadcrumbs';
import EmptyState from '@/Components/EmptyState';
import Text from '@/Components/Text';
import { Car } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface SlotEntry {
    component: string;
    props: Record<string, unknown>;
}

export default function Show({ vehicle, detailWidgets }: { vehicle: Vehicle | null; detailWidgets: SlotEntry[] }) {
    const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
    const dayAfter = new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16);

    const [pickupAt, setPickupAt] = useState(tomorrow);
    const [returnAt, setReturnAt] = useState(dayAfter);

    if (!vehicle) {
        return (
            <PublicLayout>
                <Head title="Vehicle not found" />

                <div className="mx-auto max-w-lg p-8">
                    <Breadcrumbs
                        items={[{ label: 'Our Fleet', href: route('vehicles.index') }]}
                        className="mb-4"
                    />

                    <EmptyState
                        icon={<Car className="h-10 w-10" />}
                        title="Vehicle not found"
                        description="This vehicle may no longer be available."
                        action={
                            <Link
                                href={route('vehicles.index')}
                                className="text-sm font-medium text-primary hover:text-primaryHover"
                            >
                                Browse our fleet
                            </Link>
                        }
                    />
                </div>
            </PublicLayout>
        );
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        router.get(route('bookings.checkout', vehicle.id), {
            pickup_at: pickupAt,
            return_at: returnAt,
        });
    };

    return (
        <PublicLayout>
            <Head title={`${vehicle.make} ${vehicle.model}`} />

            <div className="mx-auto max-w-lg p-8">
            <Breadcrumbs
                items={[
                    { label: 'Our Fleet', href: route('vehicles.index') },
                    { label: `${vehicle.make} ${vehicle.model}` },
                ]}
                className="mb-4"
            />

            <Link href={route('vehicles.index')} className="mb-6 inline-block text-sm text-primary">
                &larr; Back to fleet
            </Link>

            <div className="rounded-container border border-border bg-surface p-8 shadow-resting">
                <Text variant="h1" className="mb-2">
                    {vehicle.make} {vehicle.model} ({vehicle.year})
                </Text>

                <p className="mb-4 text-sm text-textMuted">
                    {vehicle.category} · {vehicle.seat_count} seats · {vehicle.transmission_type} ·{' '}
                    {vehicle.fuel_type}
                </p>

                {vehicle.location && (
                    <p className="mb-4 text-sm text-textMuted">
                        Available at {vehicle.location.name}, {vehicle.location.city}
                    </p>
                )}

                <Text variant="mono-price" className="mb-6">
                    {vehicle.daily_rate} MAD / day
                </Text>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-sm text-textMuted">Pickup</label>
                        <input
                            type="datetime-local"
                            value={pickupAt}
                            onChange={(e) => setPickupAt(e.target.value)}
                            className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                            required
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm text-textMuted">Return</label>
                        <input
                            type="datetime-local"
                            value={returnAt}
                            onChange={(e) => setReturnAt(e.target.value)}
                            className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                            required
                        />
                    </div>

                    <button
                        type="submit"
                        className="w-full rounded-interactive bg-primary px-4 py-2 font-body text-onPrimary shadow-resting hover:bg-primaryHover"
                    >
                        Book this vehicle
                    </button>
                </form>
            </div>

            <div className="mt-6">
                <SlotOutlet slot={detailWidgets} />
            </div>
            </div>
        </PublicLayout>
    );
}
