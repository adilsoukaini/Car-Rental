<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

class UnsupportedOperationException extends RuntimeException
{
    public static function for(string $operation, string $gateway): self
    {
        return new self("Gateway '{$gateway}' does not support: {$operation}");
    }
}
