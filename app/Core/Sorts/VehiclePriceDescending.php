<?php

declare(strict_types=1);

namespace App\Core\Sorts;

use App\Core\Contracts\VehicleSortOption;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sort the fleet by daily rate, most expensive first.
 */
class VehiclePriceDescending implements VehicleSortOption
{
    public function id(): string
    {
        return 'price_desc';
    }

    public function label(): string
    {
        return 'Price: High to Low';
    }

    /** @param Builder<Vehicle> $query @return Builder<Vehicle> */
    public function apply(Builder $query): Builder
    {
        return $query->orderBy('daily_rate', 'desc');
    }
}
