<?php

declare(strict_types=1);

namespace Plugins\Recommendations\Strategies;

use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Plugins\Recommendations\Contracts\RecommendationStrategy;

/**
 * Finds available vehicles in the same category, excluding the current one.
 * A vehicle with no category has no category-mates — returns empty rather
 * than matching every other uncategorised vehicle (WHERE category = NULL
 * would match nothing anyway, and matching all "unknown" vehicles as
 * "similar" would be wrong).
 */
class SameCategoryStrategy implements RecommendationStrategy
{
    public function id(): string
    {
        return 'same_category';
    }

    public function label(): string
    {
        return 'Same Category';
    }

    /**
     * @return Collection<int, Vehicle>
     */
    public function getRecommendations(Vehicle $vehicle, int $limit = 4): Collection
    {
        if (! $vehicle->category) {
            return collect();
        }

        return Vehicle::where('category', $vehicle->category)
            ->where('id', '!=', $vehicle->id)
            ->where('status', 'available')
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }
}
