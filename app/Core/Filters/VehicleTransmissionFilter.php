<?php

declare(strict_types=1);

namespace App\Core\Filters;

use App\Core\Contracts\VehicleFilterProvider;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fleet filter by transmission type (automatic/manual).
 *
 * Options are derived from the distinct transmission values present on
 * available vehicles rather than hardcoded, so a future transmission type
 * (e.g. "ev" for a single-speed electric) appears automatically.
 */
class VehicleTransmissionFilter implements VehicleFilterProvider
{
    public function id(): string
    {
        return 'transmission';
    }

    public function label(): string
    {
        return 'Transmission';
    }

    public function uiType(): string
    {
        return 'select';
    }

    /** @return array<int, array{value: mixed, label: string}> */
    public function options(): array
    {
        return Vehicle::where('status', 'available')
            ->distinct()
            ->orderBy('transmission_type')
            ->pluck('transmission_type')
            ->map(fn (string $transmission) => [
                'value' => $transmission,
                'label' => ucfirst($transmission),
            ])
            ->values()
            ->all();
    }

    /** @param Builder<Vehicle> $query @return Builder<Vehicle> */
    public function apply(Builder $query, mixed $value): Builder
    {
        return $query->whereRaw('LOWER(transmission_type) = ?', [mb_strtolower((string) $value)]);
    }
}
