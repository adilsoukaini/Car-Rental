<?php

declare(strict_types=1);

namespace Plugins\Recommendations\Support;

use Plugins\Recommendations\Contracts\RecommendationStrategy;
use Plugins\Recommendations\Strategies\SameCategoryStrategy;
use Plugins\Recommendations\Strategies\SimilarPriceStrategy;

/**
 * Resolves the active recommendation strategy from config.
 *
 * Deliberately a small config-keyed factory rather than a registry: both
 * strategies live inside this plugin, so there's nothing for a separate
 * plugin to register into yet. If a cross-plugin strategy is ever needed,
 * the interface (and the registry) moves to core — same precedent as
 * `CancellationPolicyRequest` — but building that now would serve a
 * hypothetical need (rule 6).
 */
class RecommendationStrategyResolver
{
    public static function resolve(?string $strategyId = null): RecommendationStrategy
    {
        $id = $strategyId ?? (string) config('recommendations.active_strategy', 'same_category');

        return match ($id) {
            'similar_price' => new SimilarPriceStrategy,
            default => new SameCategoryStrategy,
        };
    }
}
