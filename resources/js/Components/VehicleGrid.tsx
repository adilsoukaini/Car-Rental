import { LayoutSlot } from '@/layoutComponentRegistry';
import { Vehicle } from '@/types';

/** Static grid wrapper — the default homepage layout. */
export default function VehicleGrid({ vehicles }: { vehicles: Vehicle[] }) {
    if (!vehicles || vehicles.length === 0) return null;
    return (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {vehicles.map((v) => (
                <LayoutSlot key={v.id} name="vehicleCard" vehicle={v} />
            ))}
        </div>
    );
}
