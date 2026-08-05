<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentAuthorized
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Payment $payment) {}
}
