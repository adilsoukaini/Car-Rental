<?php

declare(strict_types=1);

namespace App\Core\Contracts;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * A single fleet-listing sort option, registered into VehicleSortRegistry.
 *
 * Mirrors the e-commerce project's ProductSortOption. A future sort option
 * ("Highest rated" once reviews mature, "Best value" once booking data
 * supports it) is a new class + one registration call, not a new match()
 * branch in VehicleController.
 */
interface VehicleSortOption
{
    /**
     * Unique slug — becomes the `sort` query-string value.
     */
    public function id(): string;

    /**
     * Human-readable label shown in the sort dropdown.
     */
    public function label(): string;

    /**
     * Apply this sort's ORDER BY clause to the query.
     *
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function apply(Builder $query): Builder;
}
