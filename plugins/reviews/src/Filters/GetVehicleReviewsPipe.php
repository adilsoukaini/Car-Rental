<?php

declare(strict_types=1);

namespace Plugins\Reviews\Filters;

use App\Models\Review;
use App\Models\Vehicle;
use Closure;

/**
 * Ported from the source project's GetProductReviewsPipe, renamed for
 * this domain. Only `is_approved` reviews are ever returned — an
 * unapproved review is invisible to everyone except staff (via
 * ReviewResource), matching the source project's moderation model exactly.
 */
class GetVehicleReviewsPipe
{
    public function __construct(private readonly Vehicle $vehicle) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, Closure $next): mixed
    {
        $reviews = Review::with('user')
            ->where('vehicle_id', $this->vehicle->id)
            ->where('is_approved', true)
            ->latest()
            ->get();

        return $next([
            'vehicleId' => $this->vehicle->id,
            'averageRating' => round((float) ($reviews->avg('rating') ?? 0), 1),
            'reviewCount' => $reviews->count(),
            'reviews' => $reviews->map(fn (Review $r) => [
                'id' => $r->id,
                'authorName' => $r->user->name,
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'isVerifiedRental' => $r->is_verified_rental,
                'createdAt' => $r->created_at->format('M j, Y'),
            ])->toArray(),
        ]);
    }
}
