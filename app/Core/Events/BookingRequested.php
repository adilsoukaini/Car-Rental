<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Booking $booking) {}
}
