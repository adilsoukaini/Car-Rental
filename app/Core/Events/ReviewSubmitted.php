<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Models\Review;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Carries the Review model, not raw ids — matches this project's own
 * convention for domain events (BookingConfirmed, BookingCancelled, etc.),
 * a deliberate adaptation from the source e-commerce project's
 * ReviewSubmitted (which carries reviewId/productId/userId/rating as raw
 * scalars) rather than a verbatim port.
 */
class ReviewSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Review $review) {}
}
