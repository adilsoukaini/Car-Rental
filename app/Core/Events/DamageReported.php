<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DamageReported
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'pickup'|'return'  $stage
     * @param  list<string>  $photoPaths
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly string $stage,
        public readonly string $description,
        public readonly array $photoPaths = [],
    ) {}
}
