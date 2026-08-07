<?php

declare(strict_types=1);

namespace App\Core\Sorts;

use App\Core\Contracts\VehicleSortOption;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sort the fleet alphabetically by make, then model.
 */
class VehicleNameAscending implements VehicleSortOption
{
    public function id(): string
    {
        return 'name_asc';
    }

    public function label(): string
    {
        return 'Name: A–Z';
    }

    /** @param Builder<Vehicle> $query @return Builder<Vehicle> */
    public function apply(Builder $query): Builder
    {
        return $query->orderBy('make', 'asc')->orderBy('model', 'asc');
    }
}
