<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Listeners;

use App\Core\Events\VehicleReturned;

/**
 * A one-way rental (pickup at Location A, return at Location B) means the
 * vehicle now physically "belongs" at B — update its home location so
 * future availability checks correctly offer it for pickup at B, not A.
 */
class RelocateVehicleOnReturn
{
    public function handle(VehicleReturned $event): void
    {
        $event->booking->vehicle->update([
            'location_id' => $event->booking->return_location_id,
        ]);
    }
}
