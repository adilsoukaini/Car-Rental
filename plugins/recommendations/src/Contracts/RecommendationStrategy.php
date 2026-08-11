<?php

declare(strict_types=1);

namespace Plugins\Recommendations\Contracts;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * A strategy that finds "similar" vehicles for the recommendations section.
 *
 * Strategies are internal to the recommendations plugin (SameCategoryStrategy
 * / SimilarPriceStrategy) and selected from config — see
 * RecommendationStrategyResolver. A future cross-plugin strategy would need
 * this interface moved to core (same precedent as
 * `CancellationPolicyRequest`), but nothing requires that yet.
 */
interface RecommendationStrategy
{
    /** Stable string id, matches config('recommendations.active_strategy'). */
    public function id(): string;

    public function label(): string;

    /**
     * Return available vehicles similar to $vehicle, never including
     * $vehicle itself.
     *
     * @return EloquentCollection<int, Vehicle>
     */
    public function getRecommendations(Vehicle $vehicle, int $limit = 4): EloquentCollection;
}
