<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Exceptions;

use RuntimeException;

class VehicleNotAvailableException extends RuntimeException
{
    public function __construct(int $vehicleId)
    {
        parent::__construct("Vehicle #{$vehicleId} is not available for the requested date range and pickup location.");
    }
}
