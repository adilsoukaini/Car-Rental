<?php

declare(strict_types=1);

namespace Plugins\Recommendations\Pipes;

use App\Models\Vehicle;
use Closure;
use Plugins\Recommendations\Support\RecommendationStrategyResolver;

/**
 * Registered on the `vehicle.recommendations` filter, invoked by
 * fleet-management's VehicleController::show() via
 * FilterRegistry::applyWithContext(). $vehicle is injected from context at
 * call time (same pattern as GetVehicleReviewsPipe on vehicle.reviews).
 *
 * Returns a plain array of recommendation card data — id, make, model,
 * category, daily_rate, and the primary image URL (null when the
 * vehicle-media plugin isn't active, so the frontend falls back to the
 * placeholder icon). The primary image is eager-loaded in ONE query for all
 * recommendations, never one query per card (rule 8).
 */
class GetVehicleRecommendationsPipe
{
    public function __construct(private readonly Vehicle $vehicle) {}

    /**
     * @param  array<mixed>  $recommendations
     * @return array<mixed>
     */
    public function handle(array $recommendations, Closure $next): array
    {
        $strategy = RecommendationStrategyResolver::resolve();
        $limit = (int) config('recommendations.max_results', 4);
        $vehicles = $strategy->getRecommendations($this->vehicle, $limit);

        // The primaryImage relation is registered dynamically by the
        // vehicle-media plugin (Vehicle::resolveRelationUsing), so it only
        // exists when that plugin is booted. isRelation() on any Vehicle
        // instance reflects the class-level registration. Guarded so the
        // pipe degrades gracefully (null imageUrl -> placeholder) instead
        // of throwing RelationNotFoundException.
        if ($this->vehicle->isRelation('primaryImage')) {
            $vehicles->load('primaryImage');
        }

        $mapped = $vehicles->map(static fn (Vehicle $v): array => [
            'id' => $v->id,
            'make' => $v->make,
            'model' => $v->model,
            'category' => $v->category,
            'daily_rate' => $v->daily_rate,
            // NOTE: must use the camelCase relation name (primaryImage), NOT
            // the snake_case primary_image. Vehicle::primaryImage is a
            // dynamically-registered relation (vehicle-media plugin); Eloquent
            // stores it under the camelCase key, and $v->primary_image resolves
            // to null in PHP (it only appears as snake_case in Inertia's JSON
            // serialization). Verified against real Postgres data.
            'imageUrl' => $v->primaryImage->url ?? null,
        ])->values()->toArray();

        return $next($mapped);
    }
}
