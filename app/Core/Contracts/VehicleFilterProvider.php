<?php

declare(strict_types=1);

namespace App\Core\Contracts;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * A single fleet-listing filter, registered into VehicleFilterRegistry.
 *
 * This is the storefront's analogue of the e-commerce project's
 * ProductFilterProvider: it declares how the filter presents to the frontend
 * (label + uiType + options) AND how it constrains the Vehicle query when a
 * value is present in the request input. Adding a new filter type later
 * (by seat count, by rating once reviews mature, etc.) means registering a
 * new provider from a ServiceProvider — never editing VehicleController.
 */
interface VehicleFilterProvider
{
    /**
     * Unique slug — becomes the query-string param name.
     */
    public function id(): string;

    /**
     * Human-readable label shown in the FilterBar.
     */
    public function label(): string;

    /**
     * 'select' | 'range' | 'checkbox' — drives generic FilterBar rendering.
     */
    public function uiType(): string;

    /**
     * For 'select': [{value, label}, …]. Null for range/checkbox types.
     *
     * @return array<int, array{value: mixed, label: string}>|null
     */
    public function options(): ?array;

    /**
     * Apply this filter's constraint to the query.
     *
     * @param  Builder<Vehicle>  $query
     * @param  mixed  $value  raw value from the request input
     * @return Builder<Vehicle>
     */
    public function apply(Builder $query, mixed $value): Builder;
}
