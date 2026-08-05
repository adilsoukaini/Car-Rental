import { Vehicle } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function Show({ vehicle }: { vehicle: Vehicle }) {
    const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
    const dayAfter = new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16);

    const [pickupAt, setPickupAt] = useState(tomorrow);
    const [returnAt, setReturnAt] = useState(dayAfter);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        router.get(route('bookings.checkout', vehicle.id), {
            pickup_at: pickupAt,
            return_at: returnAt,
        });
    };

    return (
        <div className="min-h-screen bg-background p-8 font-body text-text">
            <Head title={`${vehicle.make} ${vehicle.model}`} />

            <Link href={route('vehicles.index')} className="mb-6 inline-block text-sm text-primary">
                &larr; Back to fleet
            </Link>

            <div className="max-w-lg rounded-container border border-border bg-surface p-8 shadow-resting">
                <h1 className="mb-2 font-display text-2xl font-bold text-text">
                    {vehicle.make} {vehicle.model} ({vehicle.year})
                </h1>

                <p className="mb-4 text-sm text-textMuted">
                    {vehicle.category} · {vehicle.seat_count} seats · {vehicle.transmission_type} ·{' '}
                    {vehicle.fuel_type}
                </p>

                {vehicle.location && (
                    <p className="mb-4 text-sm text-textMuted">
                        Available at {vehicle.location.name}, {vehicle.location.city}
                    </p>
                )}

                <p className="mb-6 font-mono text-2xl font-semibold text-text">
                    {vehicle.daily_rate} MAD / day
                </p>

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
        </div>
    );
}
