<?php

declare(strict_types=1);

namespace Plugins\VehicleMedia\Pipes;

use App\Models\Vehicle;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registered on vehicle.listQuery — loads every listed vehicle's primary
 * image in one batch query, not one query per vehicle card (rule 8).
 */
class EagerLoadPrimaryImagePipe
{
    /**
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function handle(Builder $query, Closure $next): Builder
    {
        return $next($query->with('primaryImage'));
    }
}
