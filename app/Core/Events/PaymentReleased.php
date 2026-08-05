<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Distinct from PaymentRefunded: a release cancels an authorization hold
 * that was never captured — no money ever moved, unlike a refund which
 * reverses money that was actually captured.
 */
class PaymentReleased
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Payment $payment) {}
}
