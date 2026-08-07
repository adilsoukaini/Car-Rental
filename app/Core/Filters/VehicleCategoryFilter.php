<?php

declare(strict_types=1);

namespace App\Core\Filters;

use App\Core\Contracts\VehicleFilterProvider;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fleet filter by vehicle category (economy/suv/luxury/van).
 *
 * Category is still a plain string column on `vehicles` — the design doc's
 * promotion to a real vehicle_categories table is a separate, deferred phase.
 * Until then the select options are derived from the distinct category values
 * present on available vehicles, which is data-driven and stays correct as
 * new categories are added.
 */
class VehicleCategoryFilter implements VehicleFilterProvider
{
    /** @var array<string, string> */
    private const LABELS = [
        'economy' => 'Economy',
        'suv' => 'SUV',
        'luxury' => 'Luxury',
        'van' => 'Van',
    ];

    public function id(): string
    {
        return 'category';
    }

    public function label(): string
    {
        return 'Category';
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
            ->orderBy('category')
            ->pluck('category')
            ->map(fn (string $category) => [
                'value' => $category,
                'label' => self::LABELS[$category] ?? ucfirst($category),
            ])
            ->values()
            ->all();
    }

    /** @param Builder<Vehicle> $query @return Builder<Vehicle> */
    public function apply(Builder $query, mixed $value): Builder
    {
        // Case-insensitive so a hand-typed ?category=SUV matches the stored
        // lowercase 'suv' — the FilterBar itself sends the canonical lowercase
        // value, but URLs shouldn't be case-sensitive for users.
        return $query->whereRaw('LOWER(category) = ?', [mb_strtolower((string) $value)]);
    }
}
