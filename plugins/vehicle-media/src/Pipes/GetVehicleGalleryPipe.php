<?php

declare(strict_types=1);

namespace Plugins\VehicleMedia\Pipes;

use App\Models\Vehicle;
use Closure;
use Plugins\VehicleMedia\Models\VehicleImage;

/**
 * Registered on vehicle.gallery. $vehicle is injected via
 * FilterRegistry::applyWithContext() at call time, not hardcoded here.
 */
class GetVehicleGalleryPipe
{
    public function __construct(private readonly Vehicle $vehicle) {}

    /**
     * @param  array<mixed>  $images
     * @return array<mixed>
     */
    public function handle(array $images, Closure $next): array
    {
        $resolved = VehicleImage::where('vehicle_id', $this->vehicle->id)
            ->orderBy('sort_order')
            ->get()
            ->map(static function (VehicleImage $image): array {
                return [
                    'url' => VehicleImage::resolveUrl($image->path),
                    'altText' => $image->alt_text,
                    'isPrimary' => $image->is_primary,
                ];
            })
            ->values()
            ->toArray();

        return $next($resolved);
    }
}
