<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Exceptions;

use RuntimeException;

class DriverNotEligibleException extends RuntimeException
{
    public static function forUser(int $userId, string $category): self
    {
        return new self("User #{$userId} is not eligible to book a '{$category}' category vehicle.");
    }
}
