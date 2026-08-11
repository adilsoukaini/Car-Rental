<?php

declare(strict_types=1);

namespace Plugins\Recommendations\Strategies;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Plugins\Recommendations\Contracts\RecommendationStrategy;

/**
 * Finds available vehicles whose daily rate falls within ±30% of the
 * current vehicle's daily rate, excluding the current vehicle.
 */
class SimilarPriceStrategy implements RecommendationStrategy
{
    public function id(): string
    {
        return 'similar_price';
    }

    public function label(): string
    {
        return 'Similar Price';
    }

    /**
     * @return EloquentCollection<int, Vehicle>
     */
    public function getRecommendations(Vehicle $vehicle, int $limit = 4): EloquentCollection
    {
        $rate = (float) $vehicle->daily_rate;
        $min = $rate * 0.7;
        $max = $rate * 1.3;

        return Vehicle::where('id', '!=', $vehicle->id)
            ->where('status', 'available')
            ->whereBetween('daily_rate', [$min, $max])
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }
}
