<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Admin = 'admin';

    public function level(): int
    {
        return match ($this) {
            self::Customer => 0,
            self::Staff => 1,
            self::Admin => 2,
        };
    }
}
