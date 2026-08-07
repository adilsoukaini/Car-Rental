<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Support;

use Carbon\CarbonInterface;

class PriceCalculationRequest
{
    public function __construct(
        public readonly int $vehicleId,
        public readonly CarbonInterface $pickupAt,
        public readonly CarbonInterface $returnAt,
        public readonly ?int $userId = null,
        public readonly ?string $promoCode = null,
    ) {}
}
